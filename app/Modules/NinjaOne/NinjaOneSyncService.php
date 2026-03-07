<?php

namespace App\Modules\NinjaOne;

use App\Core\Database;
use App\Core\NameNormalizer;
use RuntimeException;
use Throwable;

/**
 * Service de synchronisation NinjaOne.
 *
 * Flux :
 *   1. Récupère toutes les organisations → UPSERT ninjaone_organizations (counts à 0)
 *   2. Récupère tous les équipements → agrège en mémoire par org + groupe → UPDATE counts
 *   3. Auto-mapping organisation ↔ client (similarité de nom ≥ 80%)
 *
 * Groupes de licences :
 *   RMM              → WINDOWS_SERVER, LINUX_WORKSTATION, LINUX_SERVER, MAC_SERVER, WINDOWS_WORKSTATION, MAC
 *   VMM (no license) → VMWARE_VM_HOST, VMWARE_VM_GUEST, HYPERV_VMM_HOST, HYPERV_VMM_GUEST
 *   CLOUD (no lic.)  → CLOUD_MONITOR_TARGET
 *   NMS              → NMS_*
 *   MDM              → ANDROID, APPLE_IOS, APPLE_IPADOS
 */
class NinjaOneSyncService
{
    private Database          $db;
    private NinjaOneApiClient $api;
    private int               $connectionId;
    private int               $providerId;

    private const NAME_SIMILARITY_THRESHOLD = 65;

    private const NODE_CLASS_GROUPS = [
        'RMM' => [
            'WINDOWS_SERVER',
            'LINUX_WORKSTATION',
            'LINUX_SERVER',
            'MAC_SERVER',
            'WINDOWS_WORKSTATION',
            'MAC',
        ],
        'VMM' => [
            'VMWARE_VM_HOST',
            'VMWARE_VM_GUEST',
            'HYPERV_VMM_HOST',
            'HYPERV_VMM_GUEST',
        ],
        'CLOUD_MONITORING' => [
            'CLOUD_MONITOR_TARGET',
        ],
        'NMS' => [
            'NMS_SWITCH',
            'NMS_ROUTER',
            'NMS_FIREWALL',
            'NMS_PRIVATE_NETWORK_GATEWAY',
            'NMS_PRINTER',
            'NMS_SCANNER',
            'NMS_DIAL_MANAGER',
            'NMS_WAP',
            'NMS_IPSLA',
            'NMS_COMPUTER',
            'NMS_VM_HOST',
            'NMS_APPLIANCE',
            'NMS_OTHER',
            'NMS_SERVER',
            'NMS_PHONE',
            'NMS_VIRTUAL_MACHINE',
        ],
        'MDM' => [
            'ANDROID',
            'APPLE_IOS',
            'APPLE_IPADOS',
        ],
    ];

    /** @var array<string, string> nodeClass => group */
    private array $nodeClassMap = [];

    public function __construct(Database $db, NinjaOneApiClient $api, int $connectionId)
    {
        $this->db           = $db;
        $this->api          = $api;
        $this->connectionId = $connectionId;

        // Résoudre le provider_id
        $provider = $db->fetchOne("SELECT id FROM providers WHERE code = 'ninjaone' LIMIT 1");
        if (!$provider) {
            throw new RuntimeException("Provider 'ninjaone' introuvable en base.");
        }
        $this->providerId = (int)$provider['id'];

        // Construire la map inverse nodeClass → groupe
        foreach (self::NODE_CLASS_GROUPS as $group => $classes) {
            foreach ($classes as $class) {
                $this->nodeClassMap[$class] = $group;
            }
        }
    }

    // ── Point d'entrée ──────────────────────────────────────────────

    /**
     * Synchronisation complète : organisations + comptes d'équipements.
     */
    public function syncAll(string $triggeredBy = 'web'): array
    {
        $logId = $this->startSyncLog($triggeredBy);

        $this->db->execute(
            "UPDATE provider_connections SET sync_status = 'running', updated_at = NOW() WHERE id = ?",
            [$this->connectionId]
        );

        $summary = [
            'organizations'  => ['fetched' => 0, 'created' => 0, 'updated' => 0],
            'devices_fetched' => 0,
            'errors'         => [],
        ];

        try {
            $summary['organizations'] = $this->syncOrganizations();
            $summary['devices_fetched'] = $this->syncDeviceCounts();

            $totalFetched = $summary['organizations']['fetched'] + $summary['devices_fetched'];
            $totalCreated = $summary['organizations']['created'];
            $totalUpdated = $summary['organizations']['updated'];

            $this->finishSyncLog($logId, 'success', $totalFetched, $totalCreated, $totalUpdated);

            $this->db->execute(
                "UPDATE provider_connections SET sync_status = 'success', last_sync_at = NOW(), updated_at = NOW() WHERE id = ?",
                [$this->connectionId]
            );
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $summary['errors'][] = $msg;
            $this->finishSyncLog($logId, 'error', 0, 0, 0, $msg);
            $this->db->execute(
                "UPDATE provider_connections SET sync_status = 'error', updated_at = NOW() WHERE id = ?",
                [$this->connectionId]
            );
        }

        return $summary;
    }

    // ── Organisations ───────────────────────────────────────────────

    private function syncOrganizations(): array
    {
        $organizations = $this->api->getAllOrganizations();
        $created = 0;
        $updated = 0;
        $now     = date('Y-m-d H:i:s');

        foreach ($organizations as $org) {
            $orgId = isset($org['id']) ? (int)$org['id'] : null;
            if (!$orgId) {
                continue;
            }

            $name        = $org['name']        ?? '';
            $description = $org['description'] ?? null;

            $exists = $this->db->fetchOne(
                "SELECT id FROM ninjaone_organizations
                 WHERE ninjaone_org_id = ? AND connection_id = ? LIMIT 1",
                [$orgId, $this->connectionId]
            );

            if ($exists) {
                $this->db->execute(
                    "UPDATE ninjaone_organizations
                     SET name = ?, description = ?, raw_data = ?, last_sync_at = ?, updated_at = ?
                     WHERE ninjaone_org_id = ? AND connection_id = ?",
                    [$name, $description, json_encode($org), $now, $now, $orgId, $this->connectionId]
                );
                $updated++;
            } else {
                $this->db->execute(
                    "INSERT INTO ninjaone_organizations
                        (connection_id, ninjaone_org_id, name, description, raw_data, last_sync_at)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$this->connectionId, $orgId, $name, $description, json_encode($org), $now]
                );
                $created++;
            }

            $this->tryAutoMapping($orgId, $name);
        }

        return [
            'fetched' => count($organizations),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    // ── Comptes équipements ─────────────────────────────────────────

    /**
     * Récupère tous les équipements et agrège les counts par org + groupe.
     * Met à jour ninjaone_organizations avec les counts calculés.
     *
     * @return int Nombre total d'équipements récupérés
     */
    private function syncDeviceCounts(): int
    {
        $devices = $this->api->getAllDevices();

        if (empty($devices)) {
            return 0;
        }

        // Agréger en mémoire : orgId → ['RMM' => N, 'VMM' => N, ...]
        $counts = [];
        $now    = date('Y-m-d H:i:s');

        // Délai entre appels individuels (50ms) pour respecter le rate limit
        $detailSleepUs = 50_000;

        foreach ($devices as $device) {
            $deviceId = isset($device['id']) ? (int)$device['id'] : null;
            $orgId    = isset($device['organizationId']) ? (int)$device['organizationId'] : null;
            $rawClass = $device['nodeClass'] ?? null;

            if (!$orgId || !$rawClass || !$deviceId) {
                continue;
            }

            $group = $this->nodeClassMap[$rawClass] ?? null;
            if (!$group) {
                continue; // nodeClass non reconnu
            }

            if (!isset($counts[$orgId])) {
                $counts[$orgId] = [
                    'RMM'              => 0,
                    'VMM'              => 0,
                    'NMS'              => 0,
                    'MDM'              => 0,
                    'CLOUD_MONITORING' => 0,
                ];
            }
            $counts[$orgId][$group]++;

            // Récupérer les détails complets pour manufacturer, model, lastLoggedInUser
            usleep($detailSleepUs);
            $detail = $this->api->getDeviceDetail($deviceId);

            // UPSERT dans ninjaone_devices
            $displayName    = $device['displayName'] ?? $device['systemName'] ?? $device['dnsName'] ?? '';
            $dnsName        = $device['dnsName'] ?? null;
            $isOnline       = isset($device['offline']) ? ($device['offline'] ? 0 : 1) : 0;
            $osName         = $device['os']['name'] ?? $detail['os']['name'] ?? null;
            // Marque / modèle : dans l'objet 'system' du détail
            $manufacturer   = $detail['system']['manufacturer'] ?? $detail['manufacturer'] ?? null;
            $model          = $detail['system']['model']        ?? $detail['model']        ?? null;
            $lastLoggedUser = $detail['lastLoggedInUser']       ?? null;

            // lastContact : epoch secondes ou millisecondes
            $lastContact = null;
            if (!empty($device['lastContact'])) {
                $ts = (int)$device['lastContact'];
                if ($ts > 1_000_000_000_000) {
                    $ts = intdiv($ts, 1000); // millisecondes → secondes
                }
                $lastContact = date('Y-m-d H:i:s', $ts);
            }

            $this->db->execute(
                "INSERT INTO ninjaone_devices
                    (connection_id, ninjaone_device_id, ninjaone_org_id, display_name, dns_name,
                     node_class, node_group, last_contact, is_online, os_name,
                     manufacturer, model, last_logged_user, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    ninjaone_org_id  = VALUES(ninjaone_org_id),
                    display_name     = VALUES(display_name),
                    dns_name         = VALUES(dns_name),
                    node_class       = VALUES(node_class),
                    node_group       = VALUES(node_group),
                    last_contact     = VALUES(last_contact),
                    is_online        = VALUES(is_online),
                    os_name          = VALUES(os_name),
                    manufacturer     = VALUES(manufacturer),
                    model            = VALUES(model),
                    last_logged_user = VALUES(last_logged_user),
                    updated_at       = VALUES(updated_at)",
                [
                    $this->connectionId,
                    $deviceId,
                    $orgId,
                    $displayName,
                    $dnsName,
                    $rawClass,
                    $group,
                    $lastContact,
                    $isOnline,
                    $osName,
                    $manufacturer,
                    $model,
                    $lastLoggedUser,
                    $now,
                ]
            );
        }

        // Mettre à jour les organisations en DB
        foreach ($counts as $orgId => $groupCounts) {
            $this->db->execute(
                "UPDATE ninjaone_organizations
                 SET rmm_count = ?, vmm_count = ?, nms_count = ?, mdm_count = ?, cloud_count = ?,
                     last_sync_at = ?, updated_at = ?
                 WHERE ninjaone_org_id = ? AND connection_id = ?",
                [
                    $groupCounts['RMM'],
                    $groupCounts['VMM'],
                    $groupCounts['NMS'],
                    $groupCounts['MDM'],
                    $groupCounts['CLOUD_MONITORING'],
                    $now,
                    $now,
                    $orgId,
                    $this->connectionId,
                ]
            );
        }

        // Remettre à 0 les orgs sans équipements (non présentes dans $counts)
        // pour éviter des counts obsolètes si des équipements ont été supprimés
        if (!empty($counts)) {
            $orgIds = implode(',', array_keys($counts));
            $this->db->execute(
                "UPDATE ninjaone_organizations
                 SET rmm_count = 0, vmm_count = 0, nms_count = 0, mdm_count = 0, cloud_count = 0,
                     updated_at = ?
                 WHERE connection_id = ? AND ninjaone_org_id NOT IN ({$orgIds})",
                [$now, $this->connectionId]
            );
        }

        return count($devices);
    }

    // ── Auto-mapping ────────────────────────────────────────────────

    private function tryAutoMapping(int $orgId, string $orgName): void
    {
        $orgIdStr = (string)$orgId;

        // Vérifier si déjà mappé
        $alreadyMapped = $this->db->fetchOne(
            "SELECT id FROM client_provider_mappings
             WHERE connection_id = ? AND provider_client_id = ? LIMIT 1",
            [$this->connectionId, $orgIdStr]
        );
        if ($alreadyMapped) {
            return;
        }

        // Meilleur match par similarité de nom
        $allClients = $this->db->fetchAll(
            "SELECT id, name FROM clients WHERE is_active = 1"
        );

        $bestScore    = 0;
        $bestClientId = null;

        foreach ($allClients as $client) {
            similar_text(
                NameNormalizer::normalize($orgName),
                NameNormalizer::normalize($client['name']),
                $percent
            );
            if ($percent > $bestScore) {
                $bestScore    = $percent;
                $bestClientId = (int)$client['id'];
            }
        }

        if ($bestScore >= self::NAME_SIMILARITY_THRESHOLD && $bestClientId) {
            // Vérifier qu'il n'existe pas déjà un mapping différent pour ce client/connexion/org
            $exists = $this->db->fetchOne(
                "SELECT id FROM client_provider_mappings
                 WHERE client_id = ? AND connection_id = ? AND provider_client_id = ? LIMIT 1",
                [$bestClientId, $this->connectionId, $orgIdStr]
            );
            if (!$exists) {
                $this->db->execute(
                    "INSERT INTO client_provider_mappings
                        (client_id, provider_id, connection_id, provider_client_id, provider_client_name,
                         mapping_method, is_confirmed, match_score)
                     VALUES (?, ?, ?, ?, ?, 'name_match', 0, ?)",
                    [
                        $bestClientId,
                        $this->providerId,
                        $this->connectionId,
                        $orgIdStr,
                        $orgName,
                        (int)round($bestScore),
                    ]
                );
            }
        }
    }

    // ── Sync logs ───────────────────────────────────────────────────

    private function startSyncLog(string $triggeredBy): int
    {
        $this->db->execute(
            "INSERT INTO sync_logs (provider_id, connection_id, status, triggered_by)
             VALUES (?, ?, 'running', ?)",
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
