<?php

namespace App\Modules\Eset;

use App\Core\Database;
use Throwable;

/**
 * Service de synchronisation ESET MSP Administrator 2.
 *
 * Flux :
 *   syncAll()
 *     → syncCompanies()  : upsert eset_companies + auto-mapping vers clients
 *     → syncLicenses()   : upsert eset_licenses (detail par licence)
 */
class EsetSyncService
{
    private Database $db;
    private EsetApiClient $api;
    private int $providerId;

    // Seuil similarité nom pour auto-mapping (0-100)
    private const NAME_SIMILARITY_THRESHOLD = 80;

    public function __construct(Database $db, EsetApiClient $api)
    {
        $this->db  = $db;
        $this->api = $api;

        $provider = $this->db->fetchOne(
            "SELECT id FROM providers WHERE code = 'eset' LIMIT 1"
        );
        if (!$provider) {
            throw new \RuntimeException("Fournisseur 'eset' introuvable en base.");
        }
        $this->providerId = (int)$provider['id'];
    }

    /**
     * Point d'entrée principal : sync companies puis licences.
     * Retourne un résumé de la synchronisation.
     */
    public function syncAll(string $triggeredBy = 'cron'): array
    {
        $this->log("=== Démarrage sync ESET (déclenché par: {$triggeredBy}) ===");
        $logId = $this->startSyncLog($triggeredBy);

        $summary = [
            'companies' => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'licenses'  => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'errors'    => [],
        ];

        try {
            $this->log("--- Étape 1/2 : Synchronisation des companies ---");
            $companiesSummary = $this->syncCompanies();
            $summary['companies'] = $companiesSummary;
            $this->log(sprintf(
                "Companies terminé : %d récupérées, %d créées, %d mises à jour.",
                $companiesSummary['fetched'],
                $companiesSummary['created'],
                $companiesSummary['updated']
            ));

            $this->log("--- Étape 2/2 : Synchronisation des licences ---");
            $licensesSummary = $this->syncLicenses();
            $summary['licenses'] = $licensesSummary;
            $this->log(sprintf(
                "Licences terminé : %d récupérées, %d créées, %d mises à jour.",
                $licensesSummary['fetched'],
                $licensesSummary['created'],
                $licensesSummary['updated']
            ));

            $totalFetched = $companiesSummary['fetched'] + $licensesSummary['fetched'];
            $totalCreated = $companiesSummary['created'] + $licensesSummary['created'];
            $totalUpdated = $companiesSummary['updated'] + $licensesSummary['updated'];

            $this->finishSyncLog($logId, 'success', $totalFetched, $totalCreated, $totalUpdated);

            // Mettre à jour last_sync_at du provider
            $this->db->execute(
                "UPDATE providers SET last_sync_at = NOW() WHERE id = ?",
                [$this->providerId]
            );

            $this->log("=== Sync ESET terminée avec succès ===");
        } catch (Throwable $e) {
            $summary['errors'][] = $e->getMessage();
            $this->log("=== ERREUR sync ESET : " . $e->getMessage() . " ===");
            $this->finishSyncLog($logId, 'error', 0, 0, 0, $e->getMessage());
        }

        return $summary;
    }

    // ── Companies ──────────────────────────────────────────────────

    public function syncCompanies(): array
    {
        $companies = $this->api->getAllCompanies();
        $this->log(count($companies) . " companies reçues de l'API.");
        $created   = 0;
        $updated   = 0;

        foreach ($companies as $company) {
            $companyId = $company['CompanyId'] ?? $company['companyId'] ?? null;
            if (!$companyId) {
                continue;
            }

            $name             = $company['Name']             ?? $company['name']             ?? '';
            $companyTypeId    = $company['CompanyTypeId']    ?? $company['companyTypeId']    ?? null;
            $statusId         = $company['StatusId']         ?? $company['statusId']         ?? null;
            $customIdentifier = $company['CustomIdentifier'] ?? $company['customIdentifier'] ?? null;
            $email            = $company['Email']            ?? $company['email']            ?? null;
            $vatId            = $company['VatId']            ?? $company['vatId']            ?? null;
            $description      = $company['Description']      ?? $company['description']      ?? null;
            $parentEsetId     = $company['ParentId']         ?? $company['parentId']         ?? null;

            $exists = $this->db->fetchOne(
                "SELECT id FROM eset_companies WHERE eset_company_id = ? LIMIT 1",
                [$companyId]
            );

            $now = date('Y-m-d H:i:s');

            if ($exists) {
                $this->db->execute(
                    "UPDATE eset_companies SET
                        name = ?, company_type_id = ?, status_id = ?,
                        custom_identifier = ?, email = ?, vat_id = ?,
                        description = ?, parent_eset_id = ?,
                        raw_data = ?, last_sync_at = ?, updated_at = ?
                     WHERE eset_company_id = ?",
                    [
                        $name, $companyTypeId, $statusId,
                        $customIdentifier, $email, $vatId,
                        $description, $parentEsetId,
                        json_encode($company), $now, $now,
                        $companyId,
                    ]
                );
                $updated++;
            } else {
                $this->db->execute(
                    "INSERT INTO eset_companies
                        (eset_company_id, name, company_type_id, status_id,
                         custom_identifier, email, vat_id, description,
                         parent_eset_id, raw_data, last_sync_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $companyId, $name, $companyTypeId, $statusId,
                        $customIdentifier, $email, $vatId, $description,
                        $parentEsetId, json_encode($company), $now,
                    ]
                );
                $created++;
            }

            // Auto-mapping vers clients
            $this->tryAutoMapping($companyId, $name, $customIdentifier);
        }

        return ['fetched' => count($companies), 'created' => $created, 'updated' => $updated];
    }

    // ── Licences ───────────────────────────────────────────────────

    public function syncLicenses(): array
    {
        $allCompanies = $this->db->fetchAll(
            "SELECT eset_company_id FROM eset_companies"
        );

        $totalFetched = 0;
        $created      = 0;
        $updated      = 0;
        $now          = date('Y-m-d H:i:s');

        $this->log(count($allCompanies) . " companies en base à traiter pour les licences.");

        foreach ($allCompanies as $company) {
            $companyId = $company['eset_company_id'];

            $licenses = $this->api->getLicensesByCompany($companyId);
            $count = count($licenses);
            $totalFetched += $count;
            if ($count > 0) {
                $this->log("{$count} licence(s) reçues pour company {$companyId}.");
            }

            foreach ($licenses as $lic) {
                $licenseKey = $lic['PublicLicenseKey'] ?? $lic['publicLicenseKey'] ?? null;
                if (!$licenseKey) {
                    continue;
                }

                // Utilisation directe des données de /Search/Licenses — pas d'appel /License/Detail
                $productCode    = $lic['Code']           ?? $lic['code']           ?? null;
                $productName    = $lic['Name']           ?? $lic['name']           ?? null;
                $quantity       = $lic['Quantity']       ?? $lic['quantity']       ?? 0;
                $usageCount     = $lic['UsageCount']     ?? $lic['usageCount']
                                ?? $lic['Usage']         ?? $lic['usage']         ?? 0;
                $state          = $lic['State']          ?? $lic['state']          ?? null;
                $isTrial        = !empty($lic['IsTrial']) || !empty($lic['isTrial']) ? 1 : 0;
                $expirationRaw  = $lic['ExpirationDate'] ?? $lic['expirationDate']
                                ?? $lic['TrialExpiration'] ?? $lic['trialExpiration'] ?? null;
                $expirationDate = $expirationRaw ? date('Y-m-d', strtotime($expirationRaw)) : null;

                $exists = $this->db->fetchOne(
                    "SELECT id FROM eset_licenses WHERE public_license_key = ? LIMIT 1",
                    [$licenseKey]
                );

                if ($exists) {
                    $this->db->execute(
                        "UPDATE eset_licenses SET
                            eset_company_id = ?, product_code = ?, product_name = ?,
                            quantity = ?, usage_count = ?, state = ?,
                            expiration_date = ?, is_trial = ?,
                            raw_data = ?, last_sync_at = ?, updated_at = ?
                         WHERE public_license_key = ?",
                        [
                            $companyId, $productCode, $productName,
                            $quantity, $usageCount, $state,
                            $expirationDate, $isTrial,
                            json_encode($lic), $now, $now,
                            $licenseKey,
                        ]
                    );
                    $updated++;
                } else {
                    $this->db->execute(
                        "INSERT INTO eset_licenses
                            (eset_company_id, public_license_key, product_code, product_name,
                             quantity, usage_count, state, expiration_date, is_trial,
                             raw_data, last_sync_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $companyId, $licenseKey, $productCode, $productName,
                            $quantity, $usageCount, $state, $expirationDate, $isTrial,
                            json_encode($lic), $now,
                        ]
                    );
                    $created++;
                }
            }
        }

        $this->log("Licences : {$totalFetched} récupérées, {$created} créées, {$updated} mises à jour.");

        return ['fetched' => $totalFetched, 'created' => $created, 'updated' => $updated];
    }

    // ── Auto-mapping ───────────────────────────────────────────────

    /**
     * Tente de mapper automatiquement une company ESET vers un client interne :
     * 1. Par custom_identifier = client_number (exact)
     * 2. Par similarité de nom (≥ seuil configurable)
     */
    private function tryAutoMapping(string $companyId, string $companyName, ?string $customIdentifier): void
    {
        // Note : le check d'existence se fait par (provider_id, provider_client_id, client_id)
        // pour permettre plusieurs liaisons vers des structures différentes.

        // Étape 1 : correspondance exacte par custom_identifier = client_number
        // Un même code peut exister dans plusieurs structures → créer une liaison par structure
        if (!empty($customIdentifier)) {
            $clients = $this->db->fetchAll(
                "SELECT id FROM clients WHERE client_number = ? AND is_active = 1",
                [$customIdentifier]
            );

            foreach ($clients as $client) {
                $alreadyMapped = $this->db->fetchOne(
                    "SELECT id FROM client_provider_mappings
                     WHERE provider_id = ? AND provider_client_id = ? AND client_id = ? LIMIT 1",
                    [$this->providerId, $companyId, (int)$client['id']]
                );
                if (!$alreadyMapped) {
                    $this->db->execute(
                        "INSERT INTO client_provider_mappings
                            (client_id, provider_id, provider_client_id, provider_client_name,
                             mapping_method, is_confirmed, match_score)
                         VALUES (?, ?, ?, ?, 'client_number', 0, 100)",
                        [(int)$client['id'], $this->providerId, $companyId, $companyName]
                    );
                }
            }

            // Si au moins un match trouvé, pas besoin du match par nom
            if (!empty($clients)) {
                return;
            }
        }

        // Étape 2 : similarité de nom (retourne le meilleur match unique)
        $allClients = $this->db->fetchAll(
            "SELECT id, name FROM clients WHERE is_active = 1"
        );

        $bestScore    = 0;
        $bestClientId = null;

        foreach ($allClients as $client) {
            similar_text(
                mb_strtolower($companyName),
                mb_strtolower($client['name']),
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

        $alreadyMapped = $this->db->fetchOne(
            "SELECT id FROM client_provider_mappings
             WHERE provider_id = ? AND provider_client_id = ? AND client_id = ? LIMIT 1",
            [$this->providerId, $companyId, $bestClientId]
        );
        if (!$alreadyMapped) {
            $this->db->execute(
                "INSERT INTO client_provider_mappings
                    (client_id, provider_id, provider_client_id, provider_client_name,
                     mapping_method, is_confirmed, match_score)
                 VALUES (?, ?, ?, ?, 'name_match', 0, ?)",
                [$bestClientId, $this->providerId, $companyId, $companyName, (int)round($bestScore)]
            );
        }
    }

    // ── Logger ─────────────────────────────────────────────────────

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [EsetSync] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }

    // ── Sync log helpers ───────────────────────────────────────────

    private function startSyncLog(string $triggeredBy): int
    {
        $this->db->execute(
            "INSERT INTO sync_logs (provider_id, status, triggered_by) VALUES (?, 'running', ?)",
            [$this->providerId, $triggeredBy]
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
