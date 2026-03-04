<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class LicenseController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /licenses — Récap clients × licences fournisseurs
     */
    public function index(array $params = []): void
    {
        $search         = trim($_GET['search'] ?? '');
        $tagId          = (int)($_GET['tag'] ?? 0);
        $providerFilter = $_GET['provider'] ?? '';   // '', 'eset'
        $sortBy         = $_GET['sort'] ?? 'name';
        $sortDir        = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page           = max(1, (int)($_GET['page'] ?? 1));
        $_pp     = (int)($_GET['perPage'] ?? 50);
        $perPage = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset         = ($page - 1) * $perPage;

        $allowedSorts = [
            'name'          => 'c.name',
            'client_number' => 'c.client_number',
            'eset_count'    => 'eset_lic_count',
            'eset_usage'    => 'eset_usage_pct',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        // HAVING : filtre par présence de licences fournisseur
        $havingSql = match($providerFilter) {
            'eset' => 'HAVING eset_lic_count > 0',
            default => '',
        };

        [$whereSql, $whereParams] = $this->buildWhere($search, $tagId);

        // Count : sous-requête pour appliquer le HAVING si nécessaire
        if ($havingSql) {
            $total = $this->db->count(
                "SELECT COUNT(*) FROM (
                    SELECT c.id, COUNT(DISTINCT el.id) AS eset_lic_count
                    FROM clients c
                    LEFT JOIN client_tags ct ON ct.client_id = c.id
                    LEFT JOIN client_provider_mappings cpm
                        ON cpm.client_id = c.id
                        AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
                        AND cpm.is_confirmed = 1
                    LEFT JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
                    LEFT JOIN eset_licenses el  ON el.eset_company_id = ec.eset_company_id
                    $whereSql
                    GROUP BY c.id
                    $havingSql
                ) sub",
                $whereParams
            );
        } else {
            $total = $this->db->count(
                "SELECT COUNT(DISTINCT c.id)
                 FROM clients c
                 LEFT JOIN client_tags ct ON ct.client_id = c.id
                 $whereSql",
                $whereParams
            );
        }

        $clients = $this->db->fetchAll(
            "SELECT
                c.id, c.name, c.client_number, c.is_active,
                (SELECT GROUP_CONCAT(CONCAT(t2.id, ':', t2.name, ':', t2.color)
                                     ORDER BY t2.display_order ASC SEPARATOR ';;')
                 FROM client_tags ct2 JOIN tags t2 ON t2.id = ct2.tag_id
                 WHERE ct2.client_id = c.id) AS tags_raw,
                MAX(cpm.is_confirmed)                                      AS eset_mapped,
                COUNT(DISTINCT el.id)                                      AS eset_lic_count,
                COALESCE(SUM(el.quantity), 0)                              AS eset_seats_total,
                COALESCE(SUM(el.usage_count), 0)                           AS eset_seats_used,
                ROUND(COALESCE(SUM(el.usage_count), 0)
                      / NULLIF(COALESCE(SUM(el.quantity), 0), 0) * 100)    AS eset_usage_pct
             FROM clients c
             LEFT JOIN client_tags ct ON ct.client_id = c.id
             LEFT JOIN client_provider_mappings cpm
                ON cpm.client_id = c.id
                AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
                AND cpm.is_confirmed = 1
             LEFT JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
             LEFT JOIN eset_licenses el  ON el.eset_company_id = ec.eset_company_id
             $whereSql
             GROUP BY c.id
             $havingSql
             ORDER BY $orderCol $sortDir
             LIMIT $perPage OFFSET $offset",
            $whereParams
        );

        // Détail ESET par produit, indexé par client_id
        $esetDetailsRaw = $this->db->fetchAll(
            "SELECT
                c.id AS client_id,
                COALESCE(el.product_name, 'Sans produit') AS product_name,
                SUM(el.quantity)    AS seats_total,
                SUM(el.usage_count) AS seats_used,
                COUNT(el.id)        AS lic_count
             FROM clients c
             JOIN client_provider_mappings cpm
                ON cpm.client_id = c.id
                AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
                AND cpm.is_confirmed = 1
             JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
             JOIN eset_licenses el  ON el.eset_company_id = ec.eset_company_id
             GROUP BY c.id, el.product_name
             ORDER BY c.id, el.product_name"
        );

        $esetDetails = [];
        foreach ($esetDetailsRaw as $row) {
            $esetDetails[$row['client_id']][] = $row;
        }

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $this->render('licenses/index', [
            'pageTitle'   => 'Récap Licences',
            'breadcrumbs' => ['Dashboard' => '/', 'Récap Licences' => null],
            'clients'     => $clients,
            'esetDetails' => $esetDetails,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'search'      => $search,
            'tagId'       => $tagId,
            'allTags'     => $allTags,
            'sortBy'         => $sortBy,
            'sortDir'        => $sortDir,
            'providerFilter' => $providerFilter,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function buildWhere(string $search, int $tagId): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR c.client_number LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like]);
        }

        if ($tagId > 0) {
            $conditions[] = "ct.tag_id = ?";
            $params[]     = $tagId;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
