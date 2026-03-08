<?php

namespace App\Modules\Infomaniak;

use App\Core\Database;
use App\Core\NameNormalizer;
use Throwable;

/**
 * Service de synchronisation Infomaniak.
 *
 * Flux :
 *   syncAll()
 *     → syncProducts()  : récupère /1/products, extrait les account_id uniques
 *     → syncAccounts()  : pour chaque account_id, GET /1/accounts/{id} + upsert
 *     → upsert produits dans infomaniak_products
 *     → tryAutoMapping() pour chaque compte
 */
class InformaniakSyncService
{
    private Database $db;
    private InformaniakApiClient $api;
    private int $providerId;
    private int $connectionId;

    private const NAME_SIMILARITY_THRESHOLD = 65;

    public function __construct(Database $db, InformaniakApiClient $api, int $connectionId)
    {
        $this->db           = $db;
        $this->api          = $api;
        $this->connectionId = $connectionId;

        $connection = $this->db->fetchOne(
            "SELECT id, provider_id FROM provider_connections WHERE id = ?",
            [$connectionId]
        );
        if (!$connection) {
            throw new \RuntimeException("Connexion Infomaniak #{$connectionId} introuvable en base.");
        }
        $this->providerId = (int)$connection['provider_id'];
    }

    /**
     * Point d'entrée principal.
     */
    public function syncAll(string $triggeredBy = 'cron'): array
    {
        $this->log("=== Démarrage sync Infomaniak connexion #{$this->connectionId} (déclenché par: {$triggeredBy}) ===");
        $logId = $this->startSyncLog($triggeredBy);

        $this->db->execute(
            "UPDATE provider_connections SET sync_status = 'running', updated_at = NOW() WHERE id = ?",
            [$this->connectionId]
        );

        $summary = [
            'accounts' => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'products' => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'errors'   => [],
        ];

        try {
            $this->log("--- Étape 1/2 : Récupération et sync des produits + comptes ---");
            [$accountsSummary, $productsSummary] = $this->syncProductsAndAccounts();
            $summary['accounts'] = $accountsSummary;
            $summary['products'] = $productsSummary;

            $this->log(sprintf(
                "Comptes : %d traités, %d créés, %d mis à jour.",
                $accountsSummary['fetched'],
                $accountsSummary['created'],
                $accountsSummary['updated']
            ));
            $this->log(sprintf(
                "Produits : %d récupérés, %d créés, %d mis à jour.",
                $productsSummary['fetched'],
                $productsSummary['created'],
                $productsSummary['updated']
            ));

            $totalFetched = $accountsSummary['fetched'] + $productsSummary['fetched'];
            $totalCreated = $accountsSummary['created'] + $productsSummary['created'];
            $totalUpdated = $accountsSummary['updated'] + $productsSummary['updated'];

            $this->finishSyncLog($logId, 'success', $totalFetched, $totalCreated, $totalUpdated);

            $this->db->execute(
                "UPDATE provider_connections SET last_sync_at = NOW(), sync_status = 'success', updated_at = NOW() WHERE id = ?",
                [$this->connectionId]
            );
            $this->db->execute(
                "UPDATE providers SET last_sync_at = NOW() WHERE id = ?",
                [$this->providerId]
            );

            $this->log("=== Sync Infomaniak connexion #{$this->connectionId} terminée avec succès ===");
        } catch (Throwable $e) {
            $summary['errors'][] = $e->getMessage();
            $this->log("=== ERREUR sync Infomaniak : " . $e->getMessage() . " ===");
            $this->finishSyncLog($logId, 'error', 0, 0, 0, $e->getMessage());
            $this->db->execute(
                "UPDATE provider_connections SET sync_status = 'error', updated_at = NOW() WHERE id = ?",
                [$this->connectionId]
            );
        }

        return $summary;
    }

    // ── Produits + Comptes ──────────────────────────────────────────

    private function syncProductsAndAccounts(): array
    {
        $products = $this->api->getProducts();
        $this->log(count($products) . " produits reçus de l'API.");

        $now = date('Y-m-d H:i:s');

        // Extraire les account_id uniques
        $accountIds = [];
        foreach ($products as $product) {
            $accountId = (int)($product['account_id'] ?? 0);
            if ($accountId > 0) {
                $accountIds[$accountId] = true;
            }
        }
        $accountIds = array_keys($accountIds);
        $this->log(count($accountIds) . " comptes uniques à synchroniser.");

        // Sync des comptes
        $accountsCreated = 0;
        $accountsUpdated = 0;

        foreach ($accountIds as $accountId) {
            $accountData = $this->api->getAccount($accountId);
            if (!$accountData) {
                $this->log("Compte #{$accountId} introuvable via API, ignoré.");
                continue;
            }

            $name            = $accountData['name']              ?? '';
            $legalEntityType = $accountData['legal_entity_type'] ?? null;
            $type            = $accountData['type']              ?? null;
            $isCustomer      = !empty($accountData['is_customer']) ? 1 : 0;

            $exists = $this->db->fetchOne(
                "SELECT id FROM infomaniak_accounts WHERE connection_id = ? AND infomaniak_account_id = ? LIMIT 1",
                [$this->connectionId, $accountId]
            );

            if ($exists) {
                $this->db->execute(
                    "UPDATE infomaniak_accounts SET
                        name = ?, legal_entity_type = ?, type = ?, is_customer = ?,
                        raw_data = ?, last_sync_at = ?, updated_at = ?
                     WHERE connection_id = ? AND infomaniak_account_id = ?",
                    [
                        $name, $legalEntityType, $type, $isCustomer,
                        json_encode($accountData), $now, $now,
                        $this->connectionId, $accountId,
                    ]
                );
                $accountsUpdated++;
            } else {
                $this->db->execute(
                    "INSERT INTO infomaniak_accounts
                        (connection_id, infomaniak_account_id, name, legal_entity_type, type, is_customer, raw_data, last_sync_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $this->connectionId, $accountId, $name, $legalEntityType,
                        $type, $isCustomer, json_encode($accountData), $now,
                    ]
                );
                $accountsCreated++;
            }

            // Auto-mapping vers clients
            $this->tryAutoMapping($accountId, $name);
        }

        // Sync des produits
        $productsCreated = 0;
        $productsUpdated = 0;

        foreach ($products as $product) {
            $productId   = (int)($product['id'] ?? 0);
            $accountId   = (int)($product['account_id'] ?? 0);
            if (!$productId || !$accountId) {
                continue;
            }

            $serviceId    = isset($product['service_id'])   ? (int)$product['service_id']   : null;
            $serviceName  = $product['service_name']        ?? $product['service']['name']   ?? null;
            $customerName = $product['customer_name']       ?? null;
            $internalName = $product['internal_name']       ?? $product['name']              ?? null;
            $expiredAt    = isset($product['expired_at'])   ? (int)$product['expired_at']    : null;
            $isTrial      = !empty($product['is_trial'])    ? 1 : 0;
            $isFree       = !empty($product['is_free'])     ? 1 : 0;
            $description  = $product['description']         ?? null;

            $exists = $this->db->fetchOne(
                "SELECT id FROM infomaniak_products WHERE connection_id = ? AND infomaniak_product_id = ? LIMIT 1",
                [$this->connectionId, $productId]
            );

            if ($exists) {
                $this->db->execute(
                    "UPDATE infomaniak_products SET
                        infomaniak_account_id = ?, service_id = ?, service_name = ?,
                        customer_name = ?, internal_name = ?, expired_at = ?,
                        is_trial = ?, is_free = ?, description = ?,
                        raw_data = ?, last_sync_at = ?, updated_at = ?
                     WHERE connection_id = ? AND infomaniak_product_id = ?",
                    [
                        $accountId, $serviceId, $serviceName,
                        $customerName, $internalName, $expiredAt,
                        $isTrial, $isFree, $description,
                        json_encode($product), $now, $now,
                        $this->connectionId, $productId,
                    ]
                );
                $productsUpdated++;
            } else {
                $this->db->execute(
                    "INSERT INTO infomaniak_products
                        (connection_id, infomaniak_product_id, infomaniak_account_id,
                         service_id, service_name, customer_name, internal_name,
                         expired_at, is_trial, is_free, description, raw_data, last_sync_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $this->connectionId, $productId, $accountId,
                        $serviceId, $serviceName, $customerName, $internalName,
                        $expiredAt, $isTrial, $isFree, $description, json_encode($product), $now,
                    ]
                );
                $productsCreated++;
            }
        }

        return [
            ['fetched' => count($accountIds), 'created' => $accountsCreated, 'updated' => $accountsUpdated],
            ['fetched' => count($products),   'created' => $productsCreated,  'updated' => $productsUpdated],
        ];
    }

    // ── Auto-mapping ───────────────────────────────────────────────

    private function tryAutoMapping(int $accountId, string $accountName): void
    {
        $providerClientId = (string)$accountId;

        // Vérifier si un mapping existe déjà
        $existingMapping = $this->db->fetchOne(
            "SELECT id FROM client_provider_mappings
             WHERE connection_id = ? AND provider_client_id = ? LIMIT 1",
            [$this->connectionId, $providerClientId]
        );
        if ($existingMapping) {
            return;
        }

        // Similarité de nom (≥ seuil)
        $allClients = $this->db->fetchAll(
            "SELECT id, name FROM clients WHERE is_active = 1"
        );

        $bestScore    = 0;
        $bestClientId = null;

        foreach ($allClients as $client) {
            similar_text(
                NameNormalizer::normalize($accountName),
                NameNormalizer::normalize($client['name']),
                $percent
            );
            if ($percent > $bestScore) {
                $bestScore    = $percent;
                $bestClientId = (int)$client['id'];
            }
        }

        if ($bestScore < self::NAME_SIMILARITY_THRESHOLD || $bestClientId === null) {
            return;
        }

        $this->db->execute(
            "INSERT INTO client_provider_mappings
                (client_id, provider_id, connection_id, provider_client_id, provider_client_name,
                 mapping_method, is_confirmed, match_score)
             VALUES (?, ?, ?, ?, ?, 'name_match', 0, ?)",
            [$bestClientId, $this->providerId, $this->connectionId, $providerClientId, $accountName, (int)round($bestScore)]
        );
    }

    // ── Logger ─────────────────────────────────────────────────────

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [InformaniakSync] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }

    // ── Sync log helpers ───────────────────────────────────────────

    private function startSyncLog(string $triggeredBy): int
    {
        $this->db->execute(
            "INSERT INTO sync_logs (provider_id, connection_id, status, triggered_by) VALUES (?, ?, 'running', ?)",
            [$this->providerId, $this->connectionId, $triggeredBy]
        );
        return (int)$this->db->lastInsertId();
    }

    private function finishSyncLog(
        int    $logId,
        string $status,
        int    $fetched,
        int    $created,
        int    $updated,
        string $errorMessage = ''
    ): void {
        $this->db->execute(
            "UPDATE sync_logs
             SET status = ?, finished_at = NOW(),
                 records_fetched = ?, records_created = ?, records_updated = ?,
                 error_message = ?
             WHERE id = ?",
            [$status, $fetched, $created, $updated, $errorMessage ?: null, $logId]
        );
    }
}
