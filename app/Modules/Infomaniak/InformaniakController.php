<?php

namespace App\Modules\Infomaniak;

use App\Core\Controller;
use App\Core\Database;
use App\Core\ProviderConfig;

class InformaniakController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /infomaniak/licenses — Tableau des produits Infomaniak
     */
    public function licenses(array $params = []): void
    {
        $search      = trim($_GET['search'] ?? '');
        $tagId       = (int)($_GET['tag'] ?? 0);
        $serviceName = $_GET['service_name'] ?? '';
        $sortBy      = $_GET['sort'] ?? 'client';
        $sortDir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page        = max(1, (int)($_GET['page'] ?? 1));
        $_pp         = (int)($_GET['perPage'] ?? 50);
        $perPage     = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset      = ($page - 1) * $perPage;

        $allowedSorts = [
            'client'   => 'c.name',
            'account'  => 'ia.name',
            'service'  => 'ip.service_name',
            'expires'  => 'ip.expired_at',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        [$whereSql, $whereParams] = $this->buildWhere($search, $tagId, $serviceName);

        // Requête agrégée : un compte par ligne, avec nb produits par service
        $countSql = "
            SELECT COUNT(DISTINCT ia.id)
            FROM infomaniak_accounts ia
            JOIN infomaniak_products ip ON ip.infomaniak_account_id = ia.infomaniak_account_id
                AND ip.connection_id = ia.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci
                AND cpm.connection_id = ia.connection_id
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
        ";

        $total = $this->db->count($countSql, $whereParams);

        $sql = "
            SELECT
                ia.id AS account_row_id,
                ia.infomaniak_account_id,
                ia.name AS account_name,
                ia.type AS account_type,
                ia.connection_id,
                pc.name AS connection_name,
                c.id AS client_id,
                c.name AS client_name,
                c.client_number,
                (SELECT GROUP_CONCAT(CONCAT(t.id, ':', t.name, ':', t.color)
                                     ORDER BY t.display_order ASC SEPARATOR ';;')
                 FROM client_tags ct2 JOIN tags t ON t.id = ct2.tag_id
                 WHERE ct2.client_id = c.id) AS client_tags_raw,
                cpm.is_confirmed AS mapping_confirmed,
                COUNT(DISTINCT ip.id)                                        AS product_count,
                COUNT(DISTINCT ip.service_name)                              AS service_count,
                GROUP_CONCAT(DISTINCT ip.service_name ORDER BY ip.service_name SEPARATOR ', ') AS services_list,
                MIN(ip.expired_at)                                           AS next_expiry,
                SUM(CASE WHEN ip.expired_at IS NOT NULL
                         AND ip.expired_at < UNIX_TIMESTAMP() THEN 1 ELSE 0 END) AS expired_count,
                SUM(CASE WHEN ip.expired_at IS NOT NULL
                         AND ip.expired_at BETWEEN UNIX_TIMESTAMP() AND UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 30 DAY)) THEN 1 ELSE 0 END) AS expiring_soon_count,
                ia.last_sync_at
            FROM infomaniak_accounts ia
            JOIN infomaniak_products ip ON ip.infomaniak_account_id = ia.infomaniak_account_id
                AND ip.connection_id = ia.connection_id
            JOIN provider_connections pc ON pc.id = ia.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci
                AND cpm.connection_id = ia.connection_id
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
            GROUP BY ia.id
            ORDER BY $orderCol $sortDir
            LIMIT $perPage OFFSET $offset
        ";

        $accounts = $this->db->fetchAll($sql, $whereParams);

        $lastSync = $this->db->fetchOne(
            "SELECT finished_at, status FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'infomaniak'
             AND status IN ('success','partial')
             ORDER BY finished_at DESC LIMIT 1"
        );

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $serviceNames = $this->db->fetchAll(
            "SELECT DISTINCT service_name FROM infomaniak_products
             WHERE connection_id IN (
                 SELECT pc.id FROM provider_connections pc
                 JOIN providers p ON p.id = pc.provider_id WHERE p.code = 'infomaniak'
             ) AND service_name IS NOT NULL
             ORDER BY service_name ASC"
        );

        $connections = $this->db->fetchAll(
            "SELECT pc.id, pc.name, pc.last_sync_at, pc.sync_status
             FROM provider_connections pc
             JOIN providers p ON p.id = pc.provider_id
             WHERE p.code = 'infomaniak' AND pc.is_enabled = 1
             ORDER BY pc.id ASC"
        );

        $this->render('infomaniak/licenses', [
            'pageTitle'    => 'Produits Infomaniak',
            'breadcrumbs'  => ['Dashboard' => '/', 'Infomaniak' => null, 'Produits' => null],
            'accounts'     => $accounts,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'search'       => $search,
            'tagId'        => $tagId,
            'serviceName'  => $serviceName,
            'sortBy'       => $sortBy,
            'sortDir'      => $sortDir,
            'lastSync'     => $lastSync,
            'allTags'      => $allTags,
            'serviceNames' => $serviceNames,
            'connections'  => $connections,
        ]);
    }

    /**
     * GET /infomaniak/sync-logs — Historique des synchronisations
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
             WHERE p.code = 'infomaniak'
             ORDER BY $orderCol $sortDir
             LIMIT 100"
        );

        $this->render('infomaniak/sync_logs', [
            'pageTitle'   => 'Historique sync Infomaniak',
            'breadcrumbs' => ['Dashboard' => '/', 'Infomaniak' => '/infomaniak/licenses', 'Sync logs' => null],
            'logs'        => $logs,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
        ]);
    }

    /**
     * POST /infomaniak/sync — Lance une synchronisation
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
                 WHERE pc.id = ? AND p.code = 'infomaniak'",
                [$connectionId]
            );
        } else {
            $connection = $this->db->fetchOne(
                "SELECT pc.id, pc.config_key, pc.is_enabled
                 FROM provider_connections pc
                 JOIN providers p ON p.id = pc.provider_id
                 WHERE p.code = 'infomaniak' AND pc.is_enabled = 1
                 ORDER BY pc.id ASC LIMIT 1"
            );
        }

        if (!$connection) {
            $this->json(['status' => 'error', 'message' => "Connexion Infomaniak introuvable ou désactivée."], 500);
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

        $credentials = ProviderConfig::findConnection('infomaniak', $connection['config_key']);
        if (!$credentials) {
            $this->json(['status' => 'error', 'message' => "Credentials introuvables pour config_key '{$connection['config_key']}'."], 500);
            return;
        }

        $apiToken  = $credentials['api_token'] ?? '';
        $baseUrl   = $credentials['base_url']  ?? 'https://api.infomaniak.com';

        $apiClient   = new InformaniakApiClient($apiToken, $baseUrl);
        $syncService = new InformaniakSyncService($this->db, $apiClient, $connectionId);

        $summary = $syncService->syncAll('web');

        $this->json([
            'status'  => empty($summary['errors']) ? 'success' : 'partial',
            'summary' => $summary,
        ]);
    }

    /**
     * GET /infomaniak/sync-status — Statut de la dernière synchronisation (AJAX polling)
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
                 WHERE p.code = 'infomaniak'
                 ORDER BY sl.started_at DESC LIMIT 1"
            );
        }

        $this->json([
            'running' => $latest && $latest['status'] === 'running',
            'last'    => $latest ?: null,
        ]);
    }

    /**
     * POST /infomaniak/sync-cancel — Arrêt forcé
     */
    public function syncCancel(array $params = []): void
    {
        $provider = $this->db->fetchOne("SELECT id FROM providers WHERE code = 'infomaniak' LIMIT 1");
        if (!$provider) {
            $this->json(['status' => 'error', 'message' => "Fournisseur 'infomaniak' introuvable."], 500);
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

    /**
     * GET /infomaniak/client/{id} — Produits Infomaniak d'un client spécifique
     */
    public function clientProducts(array $params = []): void
    {
        $clientId = (int)($params['id'] ?? 0);
        if (!$clientId) { http_response_code(404); exit; }

        $client = $this->db->fetchOne(
            "SELECT id, name, client_number FROM clients WHERE id = ? LIMIT 1",
            [$clientId]
        );

        if (!$client) { http_response_code(404); exit; }

        $sortBy  = $_GET['sort'] ?? 'service';
        $sortDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedSorts = [
            'service'  => 'ip.service_name',
            'name'     => 'ip.internal_name',
            'customer' => 'ip.customer_name',
            'expires'  => 'ip.expired_at',
            'account'  => 'ia.name',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'ip.service_name';

        $products = $this->db->fetchAll(
            "SELECT
                ip.id                    AS product_row_id,
                ip.infomaniak_product_id,
                ip.service_id,
                ip.service_name,
                ip.internal_name,
                ip.customer_name,
                ip.description,
                ip.expired_at,
                ip.is_trial,
                ip.is_free,
                ip.raw_data,
                ip.last_sync_at,
                ia.name                  AS account_name,
                ia.type                  AS account_type,
                ia.infomaniak_account_id,
                pc.name                  AS connection_name,
                cpm.is_confirmed         AS mapping_confirmed
             FROM client_provider_mappings cpm
             JOIN providers pr ON pr.id = cpm.provider_id AND pr.code = 'infomaniak'
             JOIN infomaniak_accounts ia
                  ON CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci = cpm.provider_client_id
                  AND ia.connection_id = cpm.connection_id
             JOIN infomaniak_products ip
                  ON ip.infomaniak_account_id = ia.infomaniak_account_id
                  AND ip.connection_id = ia.connection_id
             JOIN provider_connections pc ON pc.id = ia.connection_id
             WHERE cpm.client_id = ? AND cpm.is_confirmed = 1
             ORDER BY $orderCol $sortDir, ip.service_name ASC, ip.internal_name ASC",
            [$clientId]
        );

        $this->render('infomaniak/client_products', [
            'pageTitle'   => 'Produits Infomaniak — ' . $client['name'],
            'breadcrumbs' => [
                'Dashboard'  => '/',
                'Infomaniak' => '/infomaniak/licenses',
                htmlspecialchars($client['name']) => null,
            ],
            'client'   => $client,
            'products' => $products,
            'sortBy'   => $sortBy,
            'sortDir'  => $sortDir,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function buildWhere(string $search, int $tagId, string $serviceName): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR ia.name LIKE ? OR ip.service_name LIKE ? OR ip.internal_name LIKE ?)";
            $like         = '%' . $search . '%';
            $params       = array_merge($params, [$like, $like, $like, $like]);
        }

        if ($tagId > 0) {
            $conditions[] = "ctf.tag_id = ?";
            $params[]     = $tagId;
        }

        if ($serviceName !== '') {
            $conditions[] = "ip.service_name = ?";
            $params[]     = $serviceName;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$whereSql, $params];
    }
}
