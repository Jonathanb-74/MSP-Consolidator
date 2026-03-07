<?php

namespace App\Modules\NinjaOne;

use App\Core\Controller;
use App\Core\Database;
use App\Core\ProviderConfig;

class NinjaOneController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /ninjaone/licenses — Tableau des organisations NinjaOne avec counts de licences
     */
    public function licenses(array $params = []): void
    {
        $search    = trim($_GET['search'] ?? '');
        $tagId     = (int)($_GET['tag'] ?? 0);
        $group     = $_GET['group'] ?? '';
        $sortBy    = $_GET['sort'] ?? 'client';
        $sortDir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $_pp       = (int)($_GET['perPage'] ?? 50);
        $perPage   = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset    = ($page - 1) * $perPage;

        $allowedSorts = [
            'client'  => 'c.name',
            'org'     => 'no.name',
            'rmm'     => 'no.rmm_count',
            'nms'     => 'no.nms_count',
            'mdm'     => 'no.mdm_count',
            'vmm'     => 'no.vmm_count',
            'cloud'   => 'no.cloud_count',
            'sync'    => 'no.last_sync_at',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        [$whereSql, $whereParams] = $this->buildWhere($search, $tagId, $group);

        $countSql = "
            SELECT COUNT(*)
            FROM ninjaone_organizations no
            JOIN client_provider_mappings cpm
                 ON cpm.provider_client_id = CAST(no.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci
                 AND cpm.connection_id = no.connection_id
                 AND cpm.is_confirmed = 1
            JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
        ";
        $total = $this->db->count($countSql, $whereParams);

        $sql = "
            SELECT no.*,
                   c.id   AS client_id,
                   c.name AS client_name,
                   c.client_number,
                   GROUP_CONCAT(DISTINCT t.name ORDER BY t.display_order ASC, t.name ASC SEPARATOR '|||') AS tag_names,
                   GROUP_CONCAT(DISTINCT t.color ORDER BY t.display_order ASC, t.name ASC SEPARATOR '|||') AS tag_colors,
                   pc.name AS connection_name
            FROM ninjaone_organizations no
            JOIN client_provider_mappings cpm
                 ON cpm.provider_client_id = CAST(no.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci
                 AND cpm.connection_id = no.connection_id
                 AND cpm.is_confirmed = 1
            JOIN clients c ON c.id = cpm.client_id
            JOIN provider_connections pc ON pc.id = no.connection_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            LEFT JOIN tags t ON t.id = ctf.tag_id
            $whereSql
            GROUP BY no.id
            ORDER BY $orderCol $sortDir
            LIMIT $perPage OFFSET $offset
        ";

        $organizations = $this->db->fetchAll($sql, $whereParams);

        $lastSync = $this->db->fetchOne(
            "SELECT finished_at, status FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'ninjaone'
             AND status IN ('success','partial')
             ORDER BY finished_at DESC LIMIT 1"
        );

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $connections = $this->db->fetchAll(
            "SELECT pc.id, pc.name, pc.last_sync_at, pc.sync_status
             FROM provider_connections pc
             JOIN providers p ON p.id = pc.provider_id
             WHERE p.code = 'ninjaone' AND pc.is_enabled = 1
             ORDER BY pc.id ASC"
        );

        $this->render('ninjaone/licenses', [
            'pageTitle'     => 'Licences NinjaOne',
            'breadcrumbs'   => ['Dashboard' => '/', 'NinjaOne' => null, 'Équipements' => null],
            'organizations' => $organizations,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'search'        => $search,
            'tagId'         => $tagId,
            'group'         => $group,
            'sortBy'        => $sortBy,
            'sortDir'       => $sortDir,
            'lastSync'      => $lastSync,
            'allTags'       => $allTags,
            'connections'   => $connections,
        ]);
    }

    /**
     * GET /ninjaone/devices — Équipements NinjaOne d'un client
     */
    public function devices(array $params = []): void
    {
        $clientId = (int)($_GET['client_id'] ?? 0);
        $search   = trim($_GET['search'] ?? '');
        $group    = $_GET['group'] ?? '';
        $sortBy   = $_GET['sort'] ?? 'name';
        $sortDir  = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedSorts = [
            'name'    => 'nd.display_name',
            'org'     => 'no.name',
            'type'    => 'nd.node_class',
            'group'   => 'nd.node_group',
            'os'      => 'nd.os_name',
            'brand'   => 'nd.manufacturer',
            'user'    => 'nd.last_logged_user',
            'contact' => 'nd.last_contact',
            'online'  => 'nd.is_online',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'nd.display_name';

        $client = null;
        if ($clientId > 0) {
            $client = $this->db->fetchOne("SELECT id, name FROM clients WHERE id = ? LIMIT 1", [$clientId]);
        }

        $conditions = [
            "cpm.is_confirmed = 1",
        ];
        $params = [];

        if ($clientId > 0) {
            $conditions[] = "cpm.client_id = ?";
            $params[]     = $clientId;
        }

        if ($search !== '') {
            $conditions[] = "(nd.display_name LIKE ? OR nd.dns_name LIKE ? OR no.name LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        if ($group !== '') {
            $conditions[] = "nd.node_group = ?";
            $params[]     = $group;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $total = $this->db->count(
            "SELECT COUNT(*)
             FROM ninjaone_devices nd
             JOIN ninjaone_organizations no
                  ON no.ninjaone_org_id = nd.ninjaone_org_id AND no.connection_id = nd.connection_id
             JOIN client_provider_mappings cpm
                  ON cpm.provider_client_id = CAST(nd.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci
                  AND cpm.connection_id = nd.connection_id
             $whereSql",
            $params
        );

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $_pp     = (int)($_GET['perPage'] ?? 100);
        $perPage = in_array($_pp, [50, 100, 250, 500]) ? $_pp : 100;
        $offset  = ($page - 1) * $perPage;

        $devices = $this->db->fetchAll(
            "SELECT nd.*,
                    no.name AS org_name,
                    c.id    AS client_id,
                    c.name  AS client_name
             FROM ninjaone_devices nd
             JOIN ninjaone_organizations no
                  ON no.ninjaone_org_id = nd.ninjaone_org_id AND no.connection_id = nd.connection_id
             JOIN client_provider_mappings cpm
                  ON cpm.provider_client_id = CAST(nd.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci
                  AND cpm.connection_id = nd.connection_id
             JOIN clients c ON c.id = cpm.client_id
             $whereSql
             ORDER BY $orderCol $sortDir
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $this->render('ninjaone/devices', [
            'pageTitle' => 'Équipements NinjaOne' . ($client ? ' — ' . $client['name'] : ''),
            'breadcrumbs' => ['Dashboard' => '/', 'NinjaOne' => '/ninjaone/licenses', 'Équipements' => null],
            'devices'   => $devices,
            'client'    => $client,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'search'    => $search,
            'group'     => $group,
            'sortBy'    => $sortBy,
            'sortDir'   => $sortDir,
            'clientId'  => $clientId,
        ]);
    }

    /**
     * GET /ninjaone/sync-logs — Historique des synchronisations
     */
    public function syncLogs(array $params = []): void
    {
        $sortBy  = $_GET['sort'] ?? 'started';
        $sortDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $allowedSorts = [
            'started'    => 'sl.started_at',
            'finished'   => 'sl.finished_at',
            'trigger'    => 'sl.triggered_by',
            'status'     => 'sl.status',
            'fetched'    => 'sl.records_fetched',
            'created'    => 'sl.records_created',
            'updated'    => 'sl.records_updated',
            'connection' => 'pc.name',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'sl.started_at';

        $logs = $this->db->fetchAll(
            "SELECT sl.*, p.name AS provider_name, pc.name AS connection_name
             FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             LEFT JOIN provider_connections pc ON pc.id = sl.connection_id
             WHERE p.code = 'ninjaone'
             ORDER BY $orderCol $sortDir
             LIMIT 100"
        );

        $this->render('ninjaone/sync_logs', [
            'pageTitle'   => 'Historique sync NinjaOne',
            'breadcrumbs' => ['Dashboard' => '/', 'NinjaOne' => '/ninjaone/licenses', 'Sync logs' => null],
            'logs'        => $logs,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
        ]);
    }

    /**
     * POST /ninjaone/sync — Lance une synchronisation
     */
    public function sync(array $params = []): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $connectionId = (int)($_POST['connection_id'] ?? 0);

        if ($connectionId > 0) {
            $connection = $this->db->fetchOne(
                "SELECT pc.id, pc.config_key, pc.is_enabled
                 FROM provider_connections pc
                 JOIN providers p ON p.id = pc.provider_id
                 WHERE pc.id = ? AND p.code = 'ninjaone'",
                [$connectionId]
            );
        } else {
            $connection = $this->db->fetchOne(
                "SELECT pc.id, pc.config_key, pc.is_enabled
                 FROM provider_connections pc
                 JOIN providers p ON p.id = pc.provider_id
                 WHERE p.code = 'ninjaone' AND pc.is_enabled = 1
                 ORDER BY pc.id ASC LIMIT 1"
            );
        }

        if (!$connection) {
            $this->json(['status' => 'error', 'message' => "Connexion NinjaOne introuvable ou désactivée."], 500);
            return;
        }

        $connectionId = (int)$connection['id'];

        $running = $this->db->fetchOne(
            "SELECT id FROM sync_logs
             WHERE connection_id = ? AND status = 'running'
             AND started_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             LIMIT 1",
            [$connectionId]
        );

        if ($running) {
            $this->json(['status' => 'already_running', 'message' => 'Une synchronisation est déjà en cours pour cette connexion.']);
            return;
        }

        $credentials = ProviderConfig::findConnection('ninjaone', $connection['config_key']);
        if (!$credentials) {
            $this->json(['status' => 'error', 'message' => "Credentials introuvables pour config_key '{$connection['config_key']}'."], 500);
            return;
        }

        $tokenCache  = new NinjaOneTokenCache($credentials);
        $apiClient   = new NinjaOneApiClient($credentials, $tokenCache);
        $syncService = new NinjaOneSyncService($this->db, $apiClient, $connectionId);

        $summary = $syncService->syncAll('web');

        $this->json([
            'status'  => empty($summary['errors']) ? 'success' : 'partial',
            'summary' => $summary,
        ]);
    }

    /**
     * GET /ninjaone/sync-status — Statut de la dernière synchronisation (AJAX polling)
     */
    public function syncStatus(array $params = []): void
    {
        $connectionId = (int)($_GET['connection_id'] ?? 0);

        if ($connectionId > 0) {
            $latest = $this->db->fetchOne(
                "SELECT sl.id, sl.status, sl.started_at, sl.finished_at,
                        sl.records_fetched, sl.records_created, sl.records_updated,
                        sl.error_message, sl.triggered_by
                 FROM sync_logs sl WHERE sl.connection_id = ?
                 ORDER BY sl.started_at DESC LIMIT 1",
                [$connectionId]
            );
        } else {
            $latest = $this->db->fetchOne(
                "SELECT sl.id, sl.status, sl.started_at, sl.finished_at,
                        sl.records_fetched, sl.records_created, sl.records_updated,
                        sl.error_message, sl.triggered_by
                 FROM sync_logs sl
                 JOIN providers p ON p.id = sl.provider_id
                 WHERE p.code = 'ninjaone'
                 ORDER BY sl.started_at DESC LIMIT 1"
            );
        }

        $this->json([
            'running' => $latest && $latest['status'] === 'running',
            'last'    => $latest ?: null,
        ]);
    }

    /**
     * POST /ninjaone/sync-cancel — Arrêt forcé
     */
    public function syncCancel(array $params = []): void
    {
        $provider = $this->db->fetchOne("SELECT id FROM providers WHERE code = 'ninjaone' LIMIT 1");
        if (!$provider) {
            $this->json(['status' => 'error', 'message' => "Fournisseur 'ninjaone' introuvable."], 500);
            return;
        }

        $providerId   = (int)$provider['id'];
        $connectionId = (int)($_POST['connection_id'] ?? 0);

        if ($connectionId > 0) {
            $this->db->execute(
                "UPDATE sync_logs SET status = 'cancelled', finished_at = NOW(), error_message = 'Arrêt forcé via UI'
                 WHERE provider_id = ? AND connection_id = ? AND status = 'running'",
                [$providerId, $connectionId]
            );
            $this->db->execute(
                "UPDATE provider_connections SET sync_status = 'idle', updated_at = NOW() WHERE id = ?",
                [$connectionId]
            );
        } else {
            $this->db->execute(
                "UPDATE sync_logs SET status = 'cancelled', finished_at = NOW(), error_message = 'Arrêt forcé via UI'
                 WHERE provider_id = ? AND status = 'running'",
                [$providerId]
            );
            $this->db->execute(
                "UPDATE provider_connections SET sync_status = 'idle', updated_at = NOW() WHERE provider_id = ?",
                [$providerId]
            );
        }

        $this->json(['status' => 'cancelled', 'message' => 'Synchronisation annulée.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function buildWhere(string $search, int $tagId, string $group): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR no.name LIKE ?)";
            $like         = '%' . $search . '%';
            $params       = array_merge($params, [$like, $like]);
        }

        if ($tagId > 0) {
            $conditions[] = "ctf.tag_id = ?";
            $params[]     = $tagId;
        }

        if ($group !== '') {
            $col = match ($group) {
                'RMM'   => 'no.rmm_count',
                'NMS'   => 'no.nms_count',
                'MDM'   => 'no.mdm_count',
                'VMM'   => 'no.vmm_count',
                'CLOUD' => 'no.cloud_count',
                default => null,
            };
            if ($col) {
                $conditions[] = "$col > 0";
            }
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$whereSql, $params];
    }
}
