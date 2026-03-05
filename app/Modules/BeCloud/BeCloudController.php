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
     * GET /becloud/licenses — Tableau des subscriptions Be-Cloud
     */
    public function licenses(array $params = []): void
    {
        $search    = trim($_GET['search'] ?? '');
        $tagId     = (int)($_GET['tag'] ?? 0);
        $status    = $_GET['status'] ?? '';
        $offerType = $_GET['offer_type'] ?? '';
        $sortBy    = $_GET['sort'] ?? 'client';
        $sortDir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $_pp       = (int)($_GET['perPage'] ?? 50);
        $perPage   = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset    = ($page - 1) * $perPage;

        $allowedSorts = [
            'client'   => 'c.name',
            'customer' => 'bc.name',
            'product'  => 'bs.subscription_name',
            'quantity' => 'bs.quantity',
            'assigned' => 'bs.assigned_licenses',
            'status'   => 'bs.status',
            'end_date' => 'bs.end_date',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        [$whereSql, $whereParams] = $this->buildWhere($search, $tagId, $status, $offerType);

        $countSql = "
            SELECT COUNT(*)
            FROM be_cloud_subscriptions bs
            JOIN be_cloud_customers bc ON bc.be_cloud_customer_id = bs.be_cloud_customer_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = bc.be_cloud_customer_id
                AND cpm.connection_id = bc.connection_id
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
        ";

        $total = $this->db->count($countSql, $whereParams);

        $sql = "
            SELECT
                bs.id,
                bs.be_cloud_subscription_id,
                bs.subscription_name,
                bs.offer_name,
                bs.offer_id,
                bs.offer_type,
                bs.status,
                bs.quantity,
                bs.assigned_licenses,
                (bs.quantity - bs.assigned_licenses) AS seats_free,
                bs.start_date,
                bs.end_date,
                bs.billing_frequency,
                bs.term_duration,
                bs.is_trial,
                bs.auto_renewal,
                bs.last_sync_at,
                bc.be_cloud_customer_id,
                bc.name AS customer_name,
                bc.internal_identifier,
                bc.connection_id,
                pc.name AS connection_name,
                c.id AS client_id,
                c.name AS client_name,
                c.client_number,
                (SELECT GROUP_CONCAT(CONCAT(t.id, ':', t.name, ':', t.color)
                                     ORDER BY t.display_order ASC SEPARATOR ';;')
                 FROM client_tags ct2 JOIN tags t ON t.id = ct2.tag_id
                 WHERE ct2.client_id = c.id) AS client_tags_raw,
                cpm.is_confirmed AS mapping_confirmed,
                cpm.mapping_method
            FROM be_cloud_subscriptions bs
            JOIN be_cloud_customers bc ON bc.be_cloud_customer_id = bs.be_cloud_customer_id
            JOIN provider_connections pc ON pc.id = bc.connection_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = bc.be_cloud_customer_id
                AND cpm.connection_id = bc.connection_id
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
            GROUP BY bs.id
            ORDER BY $orderCol $sortDir
            LIMIT $perPage OFFSET $offset
        ";

        $subscriptions = $this->db->fetchAll($sql, $whereParams);

        $lastSync = $this->db->fetchOne(
            "SELECT finished_at, status FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'becloud'
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
             WHERE p.code = 'becloud' AND pc.is_enabled = 1
             ORDER BY pc.id ASC"
        );

        $this->render('becloud/licenses', [
            'pageTitle'     => 'Licences Be-Cloud',
            'breadcrumbs'   => ['Dashboard' => '/', 'Be-Cloud' => null, 'Abonnements' => null],
            'subscriptions' => $subscriptions,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'search'        => $search,
            'tagId'         => $tagId,
            'status'        => $status,
            'offerType'     => $offerType,
            'sortBy'        => $sortBy,
            'sortDir'       => $sortDir,
            'lastSync'      => $lastSync,
            'allTags'       => $allTags,
            'connections'   => $connections,
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
