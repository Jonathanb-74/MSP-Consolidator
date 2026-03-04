<?php

namespace App\Modules\Eset;

use App\Core\Controller;
use App\Core\Database;

class EsetController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /eset/licenses — Tableau des licences ESET avec filtres
     */
    public function licenses(array $params = []): void
    {
        $search  = trim($_GET['search'] ?? '');
        $tagId   = (int)($_GET['tag'] ?? 0);
        $state   = $_GET['state'] ?? '';
        $sortBy  = $_GET['sort'] ?? 'client';
        $sortDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $allowedSorts = [
            'client'   => 'c.name',
            'company'  => 'ec.name',
            'product'  => 'el.product_name',
            'quantity' => 'el.quantity',
            'usage'    => 'el.usage_count',
            'state'    => 'el.state',
            'expiry'   => 'el.expiration_date',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        [$whereSql, $whereParams] = $this->buildWhere($search, $tagId, $state);

        $countSql = "
            SELECT COUNT(*)
            FROM eset_licenses el
            JOIN eset_companies ec ON ec.eset_company_id = el.eset_company_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = ec.eset_company_id
                AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
        ";

        $total = $this->db->count($countSql, $whereParams);

        $sql = "
            SELECT
                el.id,
                el.public_license_key,
                el.product_code,
                el.product_name,
                el.quantity,
                el.usage_count,
                (el.quantity - el.usage_count) AS seats_free,
                el.state,
                el.expiration_date,
                el.is_trial,
                el.last_sync_at,
                ec.eset_company_id,
                ec.name AS company_name,
                ec.custom_identifier,
                c.id AS client_id,
                c.name AS client_name,
                c.client_number,
                (SELECT GROUP_CONCAT(CONCAT(t.id, ':', t.name, ':', t.color)
                                     ORDER BY t.display_order ASC SEPARATOR ';;')
                 FROM client_tags ct2 JOIN tags t ON t.id = ct2.tag_id
                 WHERE ct2.client_id = c.id) AS client_tags_raw,
                cpm.is_confirmed AS mapping_confirmed,
                cpm.mapping_method
            FROM eset_licenses el
            JOIN eset_companies ec ON ec.eset_company_id = el.eset_company_id
            LEFT JOIN client_provider_mappings cpm
                ON cpm.provider_client_id = ec.eset_company_id
                AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
            LEFT JOIN clients c ON c.id = cpm.client_id
            LEFT JOIN client_tags ctf ON ctf.client_id = c.id
            $whereSql
            GROUP BY el.id
            ORDER BY $orderCol $sortDir
            LIMIT $perPage OFFSET $offset
        ";

        $licenses = $this->db->fetchAll($sql, $whereParams);

        $lastSync = $this->db->fetchOne(
            "SELECT finished_at, status FROM sync_logs
             WHERE provider_id = (SELECT id FROM providers WHERE code = 'eset')
             AND status IN ('success','partial')
             ORDER BY finished_at DESC LIMIT 1"
        );

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $this->render('eset/licenses', [
            'pageTitle'   => 'Licences ESET',
            'breadcrumbs' => ['Dashboard' => '/', 'ESET' => null, 'Licences' => null],
            'licenses'    => $licenses,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'search'      => $search,
            'tagId'       => $tagId,
            'state'       => $state,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
            'lastSync'    => $lastSync,
            'allTags'     => $allTags,
        ]);
    }

    /**
     * GET /eset/sync-logs — Historique des synchronisations
     */
    public function syncLogs(array $params = []): void
    {
        $logs = $this->db->fetchAll(
            "SELECT sl.*, p.name AS provider_name
             FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'eset'
             ORDER BY sl.started_at DESC
             LIMIT 100"
        );

        $this->render('eset/sync_logs', [
            'pageTitle'   => 'Historique sync ESET',
            'breadcrumbs' => ['Dashboard' => '/', 'ESET' => '/eset/licenses', 'Sync logs' => null],
            'logs'        => $logs,
        ]);
    }

    /**
     * POST /eset/sync — Lance une synchronisation (synchrone, attend la fin)
     */
    public function sync(array $params = []): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $provider = $this->db->fetchOne(
            "SELECT id FROM providers WHERE code = 'eset' LIMIT 1"
        );

        if (!$provider) {
            $this->json(['status' => 'error', 'message' => "Fournisseur 'eset' introuvable."], 500);
            return;
        }

        $running = $this->db->fetchOne(
            "SELECT id FROM sync_logs
             WHERE provider_id = ? AND status = 'running'
             AND started_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             LIMIT 1",
            [(int)$provider['id']]
        );

        if ($running) {
            $this->json(['status' => 'already_running', 'message' => 'Une synchronisation est déjà en cours.']);
            return;
        }

        $tokenCache  = new EsetTokenCache();
        $apiClient   = new EsetApiClient($tokenCache);
        $syncService = new EsetSyncService($this->db, $apiClient);

        $summary = $syncService->syncAll('web');

        $this->json([
            'status'  => empty($summary['errors']) ? 'success' : 'partial',
            'summary' => $summary,
        ]);
    }

    /**
     * POST /eset/sync-cancel — Arrêt forcé d'une synchronisation en cours
     */
    public function syncCancel(array $params = []): void
    {
        $provider = $this->db->fetchOne(
            "SELECT id FROM providers WHERE code = 'eset' LIMIT 1"
        );

        if (!$provider) {
            $this->json(['status' => 'error', 'message' => "Fournisseur 'eset' introuvable."], 500);
            return;
        }

        $providerId = (int)$provider['id'];

        // Marquer le(s) log(s) 'running' comme cancelled
        $this->db->execute(
            "UPDATE sync_logs
             SET status = 'cancelled', finished_at = NOW(), error_message = 'Arrêt forcé via UI'
             WHERE provider_id = ? AND status = 'running'",
            [$providerId]
        );

        // Tuer le processus via le fichier PID
        $pidFile = APP_ROOT . '/storage/sync_eset.pid';
        $killed  = false;

        if (file_exists($pidFile)) {
            $pid = (int)trim(file_get_contents($pidFile));
            if ($pid > 0) {
                if (DIRECTORY_SEPARATOR === '\\') {
                    exec("taskkill /F /PID {$pid} 2>&1", $out, $code);
                    $killed = ($code === 0);
                } else {
                    $killed = posix_kill($pid, SIGTERM);
                }
            }
            @unlink($pidFile);
        }

        $this->json([
            'status'  => 'cancelled',
            'killed'  => $killed,
            'message' => $killed
                ? 'Synchronisation arrêtée et processus terminé.'
                : 'Synchronisation marquée comme annulée (processus déjà terminé ou non trouvé).',
        ]);
    }

    /**
     * GET /eset/sync-status — Statut de la dernière synchronisation (AJAX polling)
     */
    public function syncStatus(array $params = []): void
    {
        $latest = $this->db->fetchOne(
            "SELECT sl.id, sl.status, sl.started_at, sl.finished_at,
                    sl.records_fetched, sl.records_created, sl.records_updated,
                    sl.error_message, sl.triggered_by
             FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = 'eset'
             ORDER BY sl.started_at DESC LIMIT 1"
        );

        $this->json([
            'running' => $latest && $latest['status'] === 'running',
            'last'    => $latest ?: null,
        ]);
    }

    /**
     * GET /eset/debug-license — Inspecte raw_data des premières licences (diagnostic field names)
     */
    public function debugLicense(array $params = []): void
    {
        $licenses = $this->db->fetchAll(
            "SELECT id, public_license_key, quantity, usage_count, raw_data FROM eset_licenses LIMIT 3"
        );

        $result = array_map(function ($lic) {
            return [
                'id'                  => $lic['id'],
                'public_license_key'  => $lic['public_license_key'],
                'db_quantity'         => $lic['quantity'],
                'db_usage_count'      => $lic['usage_count'],
                'raw_data_keys'       => $lic['raw_data'] ? array_keys(json_decode($lic['raw_data'], true) ?? []) : null,
                'raw_data'            => $lic['raw_data'] ? json_decode($lic['raw_data'], true) : null,
            ];
        }, $licenses);

        $this->json($result);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function buildWhere(string $search, int $tagId, string $state): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR ec.name LIKE ? OR el.public_license_key LIKE ? OR el.product_name LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        if ($tagId > 0) {
            $conditions[] = "ctf.tag_id = ?";
            $params[]     = $tagId;
        }

        if ($state !== '') {
            if ($state === 'EXPIRING_SOON') {
                $conditions[] = "(el.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))";
            } else {
                $conditions[] = "el.state = ?";
                $params[]     = $state;
            }
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
