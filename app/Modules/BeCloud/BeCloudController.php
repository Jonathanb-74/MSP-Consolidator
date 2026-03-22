<?php

namespace App\Modules\BeCloud;

use App\Core\Controller;
use App\Core\Database;
use App\Core\ProviderConfig;

class BeCloudController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /becloud/customers — Liste des customers Be-Cloud avec subscriptions et licences
     */
    public function customers(array $params = []): void
    {
        $search       = trim($_GET['search'] ?? '');
        $connectionId = (int)($_GET['connection_id'] ?? 0);
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $_pp          = (int)($_GET['perPage'] ?? 50);
        $perPage      = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset       = ($page - 1) * $perPage;

        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(bcc.name LIKE ? OR c.name LIKE ?)";
            $like         = '%' . $search . '%';
            $params[]     = $like;
            $params[]     = $like;
        }
        if ($connectionId > 0) {
            $conditions[] = "bcc.connection_id = ?";
            $params[]     = $connectionId;
        }
        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "
            SELECT COUNT(DISTINCT bcc.id)
            FROM be_cloud_customers bcc
            JOIN provider_connections pc ON pc.id = bcc.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = bcc.be_cloud_customer_id
                AND cpm.connection_id = bcc.connection_id
                AND cpm.is_confirmed = 1
            LEFT JOIN clients c ON c.id = cpm.client_id
            $whereSql
        ";
        $total = $this->db->count($countSql, $params);

        $sql = "
            SELECT
                bcc.id,
                bcc.be_cloud_customer_id,
                bcc.name AS customer_name,
                bcc.internal_identifier,
                bcc.connection_id,
                pc.name AS connection_name,
                c.id    AS client_id,
                c.name  AS client_name,
                cpm.is_confirmed AS mapping_confirmed,
                cpm.mapping_method,
                (SELECT COUNT(*) FROM be_cloud_subscriptions bs
                 WHERE bs.be_cloud_customer_id = bcc.be_cloud_customer_id) AS sub_count,
                (SELECT COUNT(*) FROM be_cloud_licenses bcl
                 WHERE bcl.be_cloud_customer_id = bcc.be_cloud_customer_id
                   AND bcl.connection_id = bcc.connection_id) AS lic_count,
                (SELECT SUM(bcl2.total_licenses) FROM be_cloud_licenses bcl2
                 WHERE bcl2.be_cloud_customer_id = bcc.be_cloud_customer_id
                   AND bcl2.connection_id = bcc.connection_id) AS lic_total,
                (SELECT SUM(bcl3.consumed_licenses) FROM be_cloud_licenses bcl3
                 WHERE bcl3.be_cloud_customer_id = bcc.be_cloud_customer_id
                   AND bcl3.connection_id = bcc.connection_id) AS lic_consumed
            FROM be_cloud_customers bcc
            JOIN provider_connections pc ON pc.id = bcc.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = bcc.be_cloud_customer_id
                AND cpm.connection_id = bcc.connection_id
                AND cpm.is_confirmed = 1
            LEFT JOIN clients c ON c.id = cpm.client_id
            $whereSql
            GROUP BY bcc.id
            ORDER BY bcc.name ASC
            LIMIT $perPage OFFSET $offset
        ";
        $customers = $this->db->fetchAll($sql, $params);

        $connections = $this->db->fetchAll(
            "SELECT pc.id, pc.name FROM provider_connections pc
             JOIN providers p ON p.id = pc.provider_id
             WHERE p.code = 'becloud' AND pc.is_enabled = 1
             ORDER BY pc.id ASC"
        );

        $lastSync = $this->db->fetchOne(
            "SELECT finished_at, status FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'becloud' AND status IN ('success','partial')
             ORDER BY finished_at DESC LIMIT 1"
        );

        $this->render('becloud/customers', [
            'pageTitle'    => 'Clients Be-Cloud',
            'breadcrumbs'  => ['Dashboard' => '/', 'Be-Cloud' => '/becloud/licenses', 'Clients' => null],
            'customers'    => $customers,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'search'       => $search,
            'connectionId' => $connectionId,
            'connections'  => $connections,
            'lastSync'     => $lastSync,
        ]);
    }

    /**
     * GET /becloud/customer-detail — JSON : subscriptions + licences d'un customer (AJAX)
     */
    public function customerDetail(array $params = []): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            $this->json(['error' => 'id manquant'], 400);
            return;
        }

        $customer = $this->db->fetchOne(
            "SELECT bcc.*, pc.name AS connection_name
             FROM be_cloud_customers bcc
             JOIN provider_connections pc ON pc.id = bcc.connection_id
             WHERE bcc.id = ?",
            [$id]
        );
        if (!$customer) {
            $this->json(['error' => 'Customer introuvable'], 404);
            return;
        }

        $subscriptions = $this->db->fetchAll(
            "SELECT subscription_name, offer_name, offer_type, status,
                    quantity, assigned_licenses,
                    (quantity - assigned_licenses) AS seats_free,
                    start_date, end_date, billing_frequency, term_duration,
                    is_trial, auto_renewal
             FROM be_cloud_subscriptions
             WHERE be_cloud_customer_id = ?
             ORDER BY offer_name ASC",
            [$customer['be_cloud_customer_id']]
        );

        $licenses = $this->db->fetchAll(
            "SELECT sku_id, name, total_licenses, consumed_licenses,
                    available_licenses, suspended_licenses, is_selected
             FROM be_cloud_licenses
             WHERE be_cloud_customer_id = ? AND connection_id = ?
             ORDER BY name ASC",
            [$customer['be_cloud_customer_id'], $customer['connection_id']]
        );

        $this->json([
            'customer'      => $customer,
            'subscriptions' => $subscriptions,
            'licenses'      => $licenses,
        ]);
    }

    /**
     * GET /becloud/licenses — Tableau des licences M365 par client
     * (source : be_cloud_licenses, usage réel Microsoft)
     */
    public function licenses(array $params = []): void
    {
        $search  = trim($_GET['search'] ?? '');
        $tagId   = (int)($_GET['tag'] ?? 0);
        $sortBy  = $_GET['sort'] ?? 'client';
        $sortDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $_pp     = (int)($_GET['perPage'] ?? 50);
        $perPage = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset  = ($page - 1) * $perPage;

        $allowedSorts = [
            'client'    => 'c.name',
            'customer'  => 'bcc.name',
            'license'   => 'bcl.name',
            'total'     => 'bcl.total_licenses',
            'consumed'  => 'bcl.consumed_licenses',
            'available' => 'bcl.available_licenses',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        $conditions = [];
        $whereParams = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR bcc.name LIKE ? OR bcl.name LIKE ? OR bcl.sku_id LIKE ?)";
            $like = '%' . $search . '%';
            $whereParams = array_merge($whereParams, [$like, $like, $like, $like]);
        }
        if ($tagId > 0) {
            $conditions[] = "ctf.tag_id = ?";
            $whereParams[] = $tagId;
        }
        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "
            SELECT COUNT(DISTINCT bcl.id)
            FROM be_cloud_licenses bcl
            JOIN be_cloud_customers bcc ON bcc.be_cloud_customer_id = bcl.be_cloud_customer_id
                                       AND bcc.connection_id = bcl.connection_id
            JOIN provider_connections pc ON pc.id = bcc.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = bcc.be_cloud_customer_id
                AND cpm.connection_id = bcc.connection_id
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
        ";
        $total = $this->db->count($countSql, $whereParams);

        $sql = "
            SELECT
                bcl.id,
                bcl.sku_id,
                bcl.name AS license_name,
                bcl.total_licenses,
                bcl.consumed_licenses,
                bcl.available_licenses,
                bcl.suspended_licenses,
                bcl.last_sync_at,
                bcc.id AS bc_customer_id,
                bcc.be_cloud_customer_id,
                bcc.name AS customer_name,
                bcc.internal_identifier,
                bcc.connection_id,
                pc.name AS connection_name,
                c.id    AS client_id,
                c.name  AS client_name,
                c.client_number,
                (SELECT GROUP_CONCAT(CONCAT(t.id, ':', t.name, ':', t.color)
                                     ORDER BY t.display_order ASC SEPARATOR ';;')
                 FROM client_tags ct2 JOIN tags t ON t.id = ct2.tag_id
                 WHERE ct2.client_id = c.id) AS client_tags_raw,
                cpm.is_confirmed AS mapping_confirmed
            FROM be_cloud_licenses bcl
            JOIN be_cloud_customers bcc ON bcc.be_cloud_customer_id = bcl.be_cloud_customer_id
                                       AND bcc.connection_id = bcl.connection_id
            JOIN provider_connections pc ON pc.id = bcc.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = bcc.be_cloud_customer_id
                AND cpm.connection_id = bcc.connection_id
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
            GROUP BY bcl.id
            ORDER BY $orderCol $sortDir, bcl.name ASC
            LIMIT $perPage OFFSET $offset
        ";
        $licenses = $this->db->fetchAll($sql, $whereParams);

        $lastSync = $this->db->fetchOne(
            "SELECT finished_at, status FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'becloud' AND status IN ('success','partial')
             ORDER BY finished_at DESC LIMIT 1"
        );

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $connections = $this->db->fetchAll(
            "SELECT pc.id, pc.name, pc.last_sync_at, pc.sync_status
             FROM provider_connections pc
             JOIN providers p ON p.id = pc.provider_id
             WHERE p.code = 'becloud' AND pc.is_enabled = 1
             ORDER BY pc.id ASC"
        );

        $this->render('becloud/licenses', [
            'pageTitle'   => 'Licences Be-Cloud',
            'breadcrumbs' => ['Dashboard' => '/', 'Be-Cloud' => null, 'Licences' => null],
            'licenses'    => $licenses,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'search'      => $search,
            'tagId'       => $tagId,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
            'lastSync'    => $lastSync,
            'allTags'     => $allTags,
            'connections' => $connections,
        ]);
    }

    /**
     * GET /becloud/client/{id} — Page de détail d'un customer Be-Cloud
     * Affiche les licences M365 (usage réel) et les abonnements (avec prix)
     */
    public function clientDetail(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            http_response_code(404);
            exit;
        }

        $customer = $this->db->fetchOne(
            "SELECT bcc.*,
                    pc.name AS connection_name,
                    c.id    AS client_id,
                    c.name  AS client_name,
                    c.client_number,
                    cpm.is_confirmed AS mapping_confirmed
             FROM be_cloud_customers bcc
             JOIN provider_connections pc ON pc.id = bcc.connection_id
             LEFT JOIN client_provider_mappings cpm
                 ON cpm.provider_client_id = bcc.be_cloud_customer_id
                 AND cpm.connection_id = bcc.connection_id
             LEFT JOIN clients c ON c.id = cpm.client_id
             WHERE bcc.id = ?",
            [$id]
        );
        if (!$customer) {
            http_response_code(404);
            exit;
        }

        $licenses = $this->db->fetchAll(
            "SELECT sku_id, name, total_licenses, consumed_licenses,
                    available_licenses, suspended_licenses, is_selected,
                    last_sync_at
             FROM be_cloud_licenses
             WHERE be_cloud_customer_id = ? AND connection_id = ?
             ORDER BY name ASC",
            [$customer['be_cloud_customer_id'], $customer['connection_id']]
        );

        $subscriptions = $this->db->fetchAll(
            "SELECT subscription_name, offer_name, offer_type, status,
                    quantity, assigned_licenses,
                    start_date, end_date,
                    billing_frequency, term_duration,
                    is_trial, auto_renewal,
                    JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.listPrice.value'))          AS list_price,
                    JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.listPrice.currency.name'))  AS currency
             FROM be_cloud_subscriptions
             WHERE be_cloud_customer_id = ?
             ORDER BY offer_name ASC",
            [$customer['be_cloud_customer_id']]
        );

        $this->render('becloud/client', [
            'pageTitle'     => htmlspecialchars($customer['name']) . ' — Be-Cloud',
            'breadcrumbs'   => [
                'Dashboard'     => '/',
                'Be-Cloud'      => null,
                'Licences'      => '/becloud/licenses',
                htmlspecialchars($customer['name']) => null,
            ],
            'customer'      => $customer,
            'licenses'      => $licenses,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * GET /becloud/sync-logs — Historique des synchronisations
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
             WHERE p.code = 'becloud'
             ORDER BY $orderCol $sortDir
             LIMIT 100"
        );

        $this->render('becloud/sync_logs', [
            'pageTitle'   => 'Historique sync Be-Cloud',
            'breadcrumbs' => ['Dashboard' => '/', 'Be-Cloud' => '/becloud/licenses', 'Sync logs' => null],
            'logs'        => $logs,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
        ]);
    }

    /**
     * POST /becloud/sync — Lance une synchronisation
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
                 WHERE pc.id = ? AND p.code = 'becloud'",
                [$connectionId]
            );
        } else {
            $connection = $this->db->fetchOne(
                "SELECT pc.id, pc.config_key, pc.is_enabled
                 FROM provider_connections pc
                 JOIN providers p ON p.id = pc.provider_id
                 WHERE p.code = 'becloud' AND pc.is_enabled = 1
                 ORDER BY pc.id ASC LIMIT 1"
            );
        }

        if (!$connection) {
            $this->json(['status' => 'error', 'message' => "Connexion Be-Cloud introuvable ou désactivée."], 500);
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

        $credentials = ProviderConfig::findConnection('becloud', $connection['config_key']);
        if (!$credentials) {
            $this->json(['status' => 'error', 'message' => "Credentials introuvables pour config_key '{$connection['config_key']}'."], 500);
            return;
        }

        $tokenCache  = new BeCloudTokenCache($credentials);
        $apiClient   = new BeCloudApiClient($credentials, $tokenCache);
        $syncService = new BeCloudSyncService($this->db, $apiClient, $connectionId);

        $summary = $syncService->syncAll('web');

        $this->json([
            'status'  => empty($summary['errors']) ? 'success' : 'partial',
            'summary' => $summary,
        ]);
    }

    /**
     * GET /becloud/sync-status — Statut de la dernière synchronisation (AJAX polling)
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
                 WHERE p.code = 'becloud'
                 ORDER BY sl.started_at DESC LIMIT 1"
            );
        }

        $this->json([
            'running' => $latest && $latest['status'] === 'running',
            'last'    => $latest ?: null,
        ]);
    }

    /**
     * POST /becloud/sync-cancel — Arrêt forcé
     */
    public function syncCancel(array $params = []): void
    {
        $provider = $this->db->fetchOne("SELECT id FROM providers WHERE code = 'becloud' LIMIT 1");
        if (!$provider) {
            $this->json(['status' => 'error', 'message' => "Fournisseur 'becloud' introuvable."], 500);
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

    // ── Helpers ────────────────────────────────────────────────────

    private function buildWhere(string $search, int $tagId, string $status, string $offerType): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR bc.name LIKE ? OR bs.subscription_name LIKE ? OR bs.offer_name LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        if ($tagId > 0) {
            $conditions[] = "ctf.tag_id = ?";
            $params[]     = $tagId;
        }

        if ($status !== '') {
            if ($status === 'EXPIRING_SOON') {
                $conditions[] = "(bs.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))";
            } else {
                $conditions[] = "bs.status = ?";
                $params[]     = $status;
            }
        }

        if ($offerType !== '') {
            $conditions[] = "bs.offer_type = ?";
            $params[]     = $offerType;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$whereSql, $params];
    }
}
