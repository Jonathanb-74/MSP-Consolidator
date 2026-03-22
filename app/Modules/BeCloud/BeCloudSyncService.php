<?php

namespace App\Modules\BeCloud;

use App\Core\Database;
use App\Core\NameNormalizer;
use Throwable;

/**
 * Service de synchronisation Be-Cloud (CloudCockpit API).
 *
 * Flux :
 *   syncAll()
 *     → syncCustomers()     : upsert be_cloud_customers + auto-mapping vers clients
 *     → syncSubscriptions() : upsert be_cloud_subscriptions
 */
class BeCloudSyncService
{
    private Database $db;
    private BeCloudApiClient $api;
    private int $providerId;
    private int $connectionId;

    // Seuil similarité nom pour auto-mapping (0-100)
    private const NAME_SIMILARITY_THRESHOLD = 65;

    public function __construct(Database $db, BeCloudApiClient $api, int $connectionId)
    {
        $this->db           = $db;
        $this->api          = $api;
        $this->connectionId = $connectionId;

        $connection = $this->db->fetchOne(
            "SELECT pc.id, pc.provider_id FROM provider_connections pc WHERE pc.id = ?",
            [$connectionId]
        );
        if (!$connection) {
            throw new \RuntimeException("Connexion Be-Cloud #{$connectionId} introuvable en base.");
        }
        $this->providerId = (int)$connection['provider_id'];
    }

    /**
     * Point d'entrée principal : sync customers puis subscriptions.
     */
    public function syncAll(string $triggeredBy = 'cron'): array
    {
        $this->log("=== Démarrage sync Be-Cloud connexion #{$this->connectionId} (déclenché par: {$triggeredBy}) ===");
        $logId = $this->startSyncLog($triggeredBy);

        $this->db->execute(
            "UPDATE provider_connections SET sync_status = 'running', updated_at = NOW() WHERE id = ?",
            [$this->connectionId]
        );

        $summary = [
            'customers'     => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'subscriptions' => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'licenses'      => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'errors'        => [],
        ];

        try {
            $this->log("--- Étape 1/2 : Synchronisation des customers ---");
            $customersSummary = $this->syncCustomers();
            $summary['customers'] = $customersSummary;
            $this->log(sprintf(
                "Customers terminé : %d récupérés, %d créés, %d mis à jour.",
                $customersSummary['fetched'],
                $customersSummary['created'],
                $customersSummary['updated']
            ));

            $this->log("--- Étape 2/3 : Synchronisation des subscriptions ---");
            $subsSummary = $this->syncSubscriptions();
            $summary['subscriptions'] = $subsSummary;
            $this->log(sprintf(
                "Subscriptions terminé : %d récupérées, %d créées, %d mises à jour.",
                $subsSummary['fetched'],
                $subsSummary['created'],
                $subsSummary['updated']
            ));

            $this->log("--- Étape 3/3 : Synchronisation des licences ---");
            $licSummary = $this->syncLicenses();
            $summary['licenses'] = $licSummary;
            $this->log(sprintf(
                "Licences terminé : %d récupérées, %d créées, %d mises à jour.",
                $licSummary['fetched'],
                $licSummary['created'],
                $licSummary['updated']
            ));

            $totalFetched = $customersSummary['fetched'] + $subsSummary['fetched'] + $licSummary['fetched'];
            $totalCreated = $customersSummary['created'] + $subsSummary['created'] + $licSummary['created'];
            $totalUpdated = $customersSummary['updated'] + $subsSummary['updated'] + $licSummary['updated'];

            $this->finishSyncLog($logId, 'success', $totalFetched, $totalCreated, $totalUpdated);

            $this->db->execute(
                "UPDATE provider_connections SET last_sync_at = NOW(), sync_status = 'success', updated_at = NOW() WHERE id = ?",
                [$this->connectionId]
            );
            $this->db->execute(
                "UPDATE providers SET last_sync_at = NOW() WHERE id = ?",
                [$this->providerId]
            );

            $this->log("=== Sync Be-Cloud connexion #{$this->connectionId} terminée avec succès ===");
        } catch (Throwable $e) {
            $summary['errors'][] = $e->getMessage();
            $this->log("=== ERREUR sync Be-Cloud : " . $e->getMessage() . " ===");
            $this->finishSyncLog($logId, 'error', 0, 0, 0, $e->getMessage());
            $this->db->execute(
                "UPDATE provider_connections SET sync_status = 'error', updated_at = NOW() WHERE id = ?",
                [$this->connectionId]
            );
        }

        return $summary;
    }

    // ── Customers ──────────────────────────────────────────────────

    public function syncCustomers(): array
    {
        $customers = $this->api->getAllCustomers();
        $this->log(count($customers) . " customers reçus de l'API.");
        $created = 0;
        $updated = 0;

        foreach ($customers as $customer) {
            $customerId = $customer['id'] ?? null;
            if (!$customerId) {
                continue;
            }

            $name               = $customer['companyName']        ?? '';
            $internalIdentifier = $customer['internalIdentifier'] ?? null;
            $email              = $customer['email']              ?? null;
            $taxId              = $customer['taxId']              ?? null;
            $resellerId         = $customer['resellerId']         ?? null;

            $now    = date('Y-m-d H:i:s');
            $exists = $this->db->fetchOne(
                "SELECT id FROM be_cloud_customers WHERE connection_id = ? AND be_cloud_customer_id = ? LIMIT 1",
                [$this->connectionId, $customerId]
            );

            if ($exists) {
                $this->db->execute(
                    "UPDATE be_cloud_customers SET
                        name = ?, internal_identifier = ?, email = ?,
                        tax_id = ?, reseller_id = ?,
                        raw_data = ?, last_sync_at = ?, updated_at = ?
                     WHERE connection_id = ? AND be_cloud_customer_id = ?",
                    [
                        $name, $internalIdentifier, $email,
                        $taxId, $resellerId,
                        json_encode($customer), $now, $now,
                        $this->connectionId, $customerId,
                    ]
                );
                $updated++;
            } else {
                $this->db->execute(
                    "INSERT INTO be_cloud_customers
                        (connection_id, be_cloud_customer_id, name, internal_identifier,
                         email, tax_id, reseller_id, raw_data, last_sync_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $this->connectionId, $customerId, $name, $internalIdentifier,
                        $email, $taxId, $resellerId, json_encode($customer), $now,
                    ]
                );
                $created++;
            }

            // Auto-mapping vers clients
            $this->tryAutoMapping($customerId, $name, $internalIdentifier);
        }

        return ['fetched' => count($customers), 'created' => $created, 'updated' => $updated];
    }

    // ── Subscriptions ──────────────────────────────────────────────

    public function syncSubscriptions(): array
    {
        $allCustomers = $this->db->fetchAll(
            "SELECT be_cloud_customer_id FROM be_cloud_customers WHERE connection_id = ?",
            [$this->connectionId]
        );

        $totalFetched = 0;
        $created      = 0;
        $updated      = 0;
        $now          = date('Y-m-d H:i:s');

        $this->log(count($allCustomers) . " customers en base à traiter pour les subscriptions.");

        foreach ($allCustomers as $customer) {
            $customerId    = $customer['be_cloud_customer_id'];
            $subscriptions = $this->api->getSubscriptionsByCustomer($customerId);
            $count         = count($subscriptions);
            $totalFetched += $count;

            if ($count > 0) {
                $this->log("{$count} subscription(s) reçues pour customer {$customerId}.");
            }

            foreach ($subscriptions as $sub) {
                $subscriptionId = $sub['id'] ?? null;
                if (!$subscriptionId) {
                    continue;
                }

                $subscriptionName   = $sub['subscriptionName'] ?? $sub['offerName'] ?? null;
                $offerName          = $sub['offerName']        ?? null;
                $offerId            = $sub['offerId']          ?? null;
                $offerType          = $sub['offerType']['name'] ?? null;
                $status             = $sub['subscriptionStatus']['name'] ?? null;
                $quantity           = (int)($sub['quantity']         ?? 0);
                $assignedLicenses   = (int)($sub['assignedLicenses'] ?? 0);
                $startDate          = isset($sub['startDate'])  ? date('Y-m-d', strtotime($sub['startDate']))  : null;
                $endDate            = isset($sub['endDate'])    ? date('Y-m-d', strtotime($sub['endDate']))    : null;
                $billingFrequency   = $sub['billingFrequency']['name'] ?? null;
                $termDuration       = $sub['termDuration']['name']     ?? null;
                $isTrial            = !empty($sub['isTrialOffer']) ? 1 : 0;
                $autoRenewal        = !empty($sub['autoRenewal'])  ? 1 : 0;
                $providerInstanceId = $sub['providerInstanceId'] ?? null;

                $exists = $this->db->fetchOne(
                    "SELECT id FROM be_cloud_subscriptions WHERE be_cloud_subscription_id = ? LIMIT 1",
                    [$subscriptionId]
                );

                if ($exists) {
                    $this->db->execute(
                        "UPDATE be_cloud_subscriptions SET
                            be_cloud_customer_id = ?, subscription_name = ?, offer_name = ?,
                            offer_id = ?, offer_type = ?, status = ?,
                            quantity = ?, assigned_licenses = ?,
                            start_date = ?, end_date = ?,
                            billing_frequency = ?, term_duration = ?,
                            is_trial = ?, auto_renewal = ?, provider_instance_id = ?,
                            raw_data = ?, last_sync_at = ?, updated_at = ?
                         WHERE be_cloud_subscription_id = ?",
                        [
                            $customerId, $subscriptionName, $offerName,
                            $offerId, $offerType, $status,
                            $quantity, $assignedLicenses,
                            $startDate, $endDate,
                            $billingFrequency, $termDuration,
                            $isTrial, $autoRenewal, $providerInstanceId,
                            json_encode($sub), $now, $now,
                            $subscriptionId,
                        ]
                    );
                    $updated++;
                } else {
                    $this->db->execute(
                        "INSERT INTO be_cloud_subscriptions
                            (be_cloud_customer_id, be_cloud_subscription_id,
                             subscription_name, offer_name, offer_id, offer_type, status,
                             quantity, assigned_licenses, start_date, end_date,
                             billing_frequency, term_duration, is_trial, auto_renewal,
                             provider_instance_id, raw_data, last_sync_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $customerId, $subscriptionId,
                            $subscriptionName, $offerName, $offerId, $offerType, $status,
                            $quantity, $assignedLicenses, $startDate, $endDate,
                            $billingFrequency, $termDuration, $isTrial, $autoRenewal,
                            $providerInstanceId, json_encode($sub), $now,
                        ]
                    );
                    $created++;
                }
            }
        }

        $this->log("Subscriptions : {$totalFetched} récupérées, {$created} créées, {$updated} mises à jour.");
        return ['fetched' => $totalFetched, 'created' => $created, 'updated' => $updated];
    }

    // ── Licenses ───────────────────────────────────────────────────

    /**
     * Synchronise les licences M365/cloud pour chaque customer ayant un providerInstanceId.
     * Appelle GET /v1/Customers/{id}/licenses?providerInstanceId={id}
     */
    public function syncLicenses(): array
    {
        $allCustomers = $this->db->fetchAll(
            "SELECT bcc.be_cloud_customer_id, bcc.connection_id,
                    bs.provider_instance_id
             FROM be_cloud_customers bcc
             JOIN be_cloud_subscriptions bs
                  ON bs.be_cloud_customer_id = bcc.be_cloud_customer_id
             WHERE bcc.connection_id = ?
               AND bs.provider_instance_id IS NOT NULL
               AND bs.provider_instance_id != ''
             GROUP BY bcc.be_cloud_customer_id, bcc.connection_id, bs.provider_instance_id",
            [$this->connectionId]
        );

        $totalFetched = 0;
        $created      = 0;
        $updated      = 0;
        $now          = date('Y-m-d H:i:s');

        $this->log(count($allCustomers) . " customers avec providerInstanceId à traiter pour les licences.");

        foreach ($allCustomers as $row) {
            $customerId         = $row['be_cloud_customer_id'];
            $providerInstanceId = $row['provider_instance_id'];

            try {
                $licenses = $this->api->getLicensesByCustomer($customerId, $providerInstanceId);
            } catch (\Throwable $e) {
                $this->log("Licences ignorées pour customer {$customerId} : " . $e->getMessage());
                continue;
            }

            $count    = count($licenses);
            $totalFetched += $count;

            if ($count > 0) {
                $this->log("{$count} licence(s) reçue(s) pour customer {$customerId}.");
            }

            foreach ($licenses as $lic) {
                $skuId              = $lic['skuId']              ?? null;
                if (!$skuId) {
                    continue;
                }
                $name               = $lic['name']               ?? null;
                $totalLicenses      = (int)($lic['totalLicenses']     ?? 0);
                $consumedLicenses   = (int)($lic['consumedLicenses']  ?? 0);
                $availableLicenses  = (int)($lic['availableLicenses'] ?? 0);
                $suspendedLicenses  = (int)($lic['suspendedLicenses'] ?? 0);
                $isSelected         = !empty($lic['isSelected']) ? 1 : 0;

                $exists = $this->db->fetchOne(
                    "SELECT id FROM be_cloud_licenses
                     WHERE connection_id = ? AND be_cloud_customer_id = ? AND sku_id = ?
                     LIMIT 1",
                    [$this->connectionId, $customerId, $skuId]
                );

                if ($exists) {
                    $this->db->execute(
                        "UPDATE be_cloud_licenses SET
                            name = ?, total_licenses = ?, consumed_licenses = ?,
                            available_licenses = ?, suspended_licenses = ?, is_selected = ?,
                            raw_data = ?, last_sync_at = ?, updated_at = ?
                         WHERE connection_id = ? AND be_cloud_customer_id = ? AND sku_id = ?",
                        [
                            $name, $totalLicenses, $consumedLicenses,
                            $availableLicenses, $suspendedLicenses, $isSelected,
                            json_encode($lic), $now, $now,
                            $this->connectionId, $customerId, $skuId,
                        ]
                    );
                    $updated++;
                } else {
                    $this->db->execute(
                        "INSERT INTO be_cloud_licenses
                            (connection_id, be_cloud_customer_id, sku_id, name,
                             total_licenses, consumed_licenses, available_licenses,
                             suspended_licenses, is_selected, raw_data, last_sync_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $this->connectionId, $customerId, $skuId, $name,
                            $totalLicenses, $consumedLicenses, $availableLicenses,
                            $suspendedLicenses, $isSelected, json_encode($lic), $now,
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

    private function tryAutoMapping(string $customerId, string $customerName, ?string $internalIdentifier): void
    {
        // Étape 1 : correspondance exacte par internal_identifier = client_number
        if (!empty($internalIdentifier)) {
            $clients = $this->db->fetchAll(
                "SELECT id FROM clients WHERE client_number = ? AND is_active = 1",
                [$internalIdentifier]
            );

            foreach ($clients as $client) {
                $alreadyMapped = $this->db->fetchOne(
                    "SELECT id FROM client_provider_mappings
                     WHERE connection_id = ? AND provider_client_id = ? AND client_id = ? LIMIT 1",
                    [$this->connectionId, $customerId, (int)$client['id']]
                );
                if (!$alreadyMapped) {
                    $this->db->execute(
                        "INSERT INTO client_provider_mappings
                            (client_id, provider_id, connection_id, provider_client_id, provider_client_name,
                             mapping_method, is_confirmed, match_score)
                         VALUES (?, ?, ?, ?, ?, 'client_number', 0, 100)",
                        [(int)$client['id'], $this->providerId, $this->connectionId, $customerId, $customerName]
                    );
                }
            }

            if (!empty($clients)) {
                return;
            }
        }

        // Étape 2 : similarité de nom (≥ seuil)
        $allClients = $this->db->fetchAll(
            "SELECT id, name FROM clients WHERE is_active = 1"
        );

        $bestScore    = 0;
        $bestClientId = null;

        foreach ($allClients as $client) {
            similar_text(
                NameNormalizer::normalize($customerName),
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

        $alreadyMapped = $this->db->fetchOne(
            "SELECT id FROM client_provider_mappings
             WHERE connection_id = ? AND provider_client_id = ? AND client_id = ? LIMIT 1",
            [$this->connectionId, $customerId, $bestClientId]
        );
        if (!$alreadyMapped) {
            $this->db->execute(
                "INSERT INTO client_provider_mappings
                    (client_id, provider_id, connection_id, provider_client_id, provider_client_name,
                     mapping_method, is_confirmed, match_score)
                 VALUES (?, ?, ?, ?, ?, 'name_match', 0, ?)",
                [$bestClientId, $this->providerId, $this->connectionId, $customerId, $customerName, (int)round($bestScore)]
            );
        }
    }

    // ── Logger ─────────────────────────────────────────────────────

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [BeCloudSync] {$message}";
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
