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
        $search  = trim($_GET['search'] ?? '');
        $sortBy  = $_GET['sort'] ?? 'name';
        $sortDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $_pp     = (int)($_GET['perPage'] ?? 50);
        $perPage = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset  = ($page - 1) * $perPage;
        $showAll      = isset($_GET['show_all']) && $_GET['show_all'] === '1';
        $tagLogic      = ($_GET['tag_logic'] ?? '') === 'and' ? 'and' : 'or';
        $providerLogic = ($_GET['provider_logic'] ?? '') === 'and' ? 'and' : 'or';

        // Tags : multi-sélection (tags[]=1&tags[]=2) + compat ancien param tag=
        $tagIds = array_values(array_unique(array_filter(array_map('intval', (array)($_GET['tags'] ?? [])))));
        if (empty($tagIds) && isset($_GET['tag']) && (int)$_GET['tag'] > 0) {
            $tagIds = [(int)$_GET['tag']];
        }

        // Fournisseurs : multi-sélection (providers[]=eset&providers[]=becloud) + compat provider=
        $providerFilters = array_values(array_intersect(
            (array)($_GET['providers'] ?? []),
            ['eset', 'becloud', 'ninjaone', 'infomaniak']
        ));
        if (empty($providerFilters) && !empty($_GET['provider'])) {
            $pv = $_GET['provider'];
            if (in_array($pv, ['eset', 'becloud', 'ninjaone', 'infomaniak'])) {
                $providerFilters = [$pv];
            }
        }

        $allowedSorts = [
            'name'          => 'c.name',
            'client_number' => 'c.client_number',
            'eset_count'    => 'eset_lic_count',
            'eset_usage'    => 'eset_usage_pct',
            'bc_count'      => 'bc_sub_count',
            'ninja_rmm'     => 'ninja_rmm',
            'ninja_nms'     => 'ninja_nms',
            'ninja_mdm'     => 'ninja_mdm',
            'ik_count'      => 'ik_product_count',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        [$whereSql, $whereParams, $tagHavingSql] = $this->buildWhere($search, $tagIds, $tagLogic);
        $havingSql = $this->buildHaving($providerFilters, $showAll, $providerLogic, $tagHavingSql);

        // Joins toujours LEFT (le filtrage se fait via HAVING)
        $bcJoinSql       = $this->bcJoin();
        $bcLicJoinSql    = $this->bcLicJoin();
        $ninjaJoinSql    = $this->ninjaJoin();
        $ikJoinSql       = $this->ikJoin();

        // Count via sous-requête pour réutiliser la même logique
        $total = $this->db->count(
            "SELECT COUNT(*) FROM (
                SELECT c.id
                FROM clients c
                LEFT JOIN client_tags ct ON ct.client_id = c.id
                LEFT JOIN client_provider_mappings cpm
                    ON cpm.client_id = c.id
                    AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
                    AND cpm.is_confirmed = 1
                LEFT JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
                LEFT JOIN eset_licenses el  ON el.eset_company_id = ec.eset_company_id
                {$bcJoinSql}
                {$bcLicJoinSql}
                {$ninjaJoinSql}
                {$ikJoinSql}
                $whereSql
                GROUP BY c.id
                $havingSql
            ) sub",
            $whereParams
        );

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
                      / NULLIF(COALESCE(SUM(el.quantity), 0), 0) * 100)    AS eset_usage_pct,
                COALESCE(bc_agg.bc_sub_count, 0)                           AS bc_sub_count,
                COALESCE(bc_agg.bc_seats_total, 0)                         AS bc_seats_total,
                COALESCE(bc_agg.bc_seats_used, 0)                          AS bc_seats_used,
                COALESCE(bc_lic_agg.bc_lic_count, 0)                       AS bc_lic_count,
                COALESCE(bc_lic_agg.bc_lic_total, 0)                       AS bc_lic_total,
                COALESCE(bc_lic_agg.bc_lic_consumed, 0)                    AS bc_lic_consumed,
                COALESCE(ninja_agg.ninja_rmm,   0)                         AS ninja_rmm,
                COALESCE(ninja_agg.ninja_nms,   0)                         AS ninja_nms,
                COALESCE(ninja_agg.ninja_mdm,   0)                         AS ninja_mdm,
                COALESCE(ninja_agg.ninja_vmm,   0)                         AS ninja_vmm,
                COALESCE(ninja_agg.ninja_cloud, 0)                         AS ninja_cloud,
                COALESCE(ik_agg.ik_product_count, 0)                       AS ik_product_count,
                COALESCE(ik_agg.ik_account_count, 0)                       AS ik_account_count
             FROM clients c
             LEFT JOIN client_tags ct ON ct.client_id = c.id
             LEFT JOIN client_provider_mappings cpm
                ON cpm.client_id = c.id
                AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
                AND cpm.is_confirmed = 1
             LEFT JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
             LEFT JOIN eset_licenses el  ON el.eset_company_id = ec.eset_company_id
             {$bcJoinSql}
             {$bcLicJoinSql}
             {$ninjaJoinSql}
             {$ikJoinSql}
             $whereSql
             GROUP BY c.id
             $havingSql
             ORDER BY $orderCol $sortDir
             LIMIT $perPage OFFSET $offset",
            $whereParams
        );

        // Détail Be-Cloud licences M365, indexé par client_id
        $bcLicDetailsRaw = $this->db->fetchAll(
            "SELECT cpm2.client_id,
                    bcl.sku_id,
                    bcl.name,
                    bcl.total_licenses,
                    bcl.consumed_licenses,
                    bcl.available_licenses,
                    bcl.suspended_licenses
             FROM be_cloud_licenses bcl
             JOIN be_cloud_customers bcc
                  ON bcc.be_cloud_customer_id = bcl.be_cloud_customer_id
                  AND bcc.connection_id = bcl.connection_id
             JOIN client_provider_mappings cpm2
                  ON cpm2.connection_id = bcc.connection_id
                  AND cpm2.provider_client_id = bcc.be_cloud_customer_id
                  AND cpm2.is_confirmed = 1
             ORDER BY cpm2.client_id, bcl.name"
        );

        $bcLicDetails = [];
        foreach ($bcLicDetailsRaw as $row) {
            $bcLicDetails[$row['client_id']][] = $row;
        }

        // Détail Be-Cloud abonnements, indexé par client_id
        $bcSubDetailsRaw = $this->db->fetchAll(
            "SELECT cpm2.client_id,
                    bs.offer_name,
                    bs.status,
                    bs.quantity,
                    bs.start_date,
                    bs.end_date,
                    bs.billing_frequency,
                    bs.term_duration,
                    JSON_UNQUOTE(JSON_EXTRACT(bs.raw_data, '$.listPrice.value'))         AS list_price,
                    JSON_UNQUOTE(JSON_EXTRACT(bs.raw_data, '$.listPrice.currency.name')) AS currency
             FROM be_cloud_subscriptions bs
             JOIN be_cloud_customers bcc
                  ON bcc.be_cloud_customer_id = bs.be_cloud_customer_id
             JOIN client_provider_mappings cpm2
                  ON cpm2.connection_id = bcc.connection_id
                  AND cpm2.provider_client_id = bcc.be_cloud_customer_id
                  AND cpm2.is_confirmed = 1
             ORDER BY cpm2.client_id, bs.offer_name"
        );

        $bcSubDetails = [];
        foreach ($bcSubDetailsRaw as $row) {
            $bcSubDetails[$row['client_id']][] = $row;
        }

        // Détail ESET par produit, indexé par client_id
        $esetDetailsRaw = $this->db->fetchAll(
            "SELECT
                c.id AS client_id,
                COALESCE(el.product_name, 'Sans produit') AS product_name,
                SUM(el.quantity)    AS seats_total,
                SUM(el.usage_count) AS seats_used,
                COUNT(el.id)        AS lic_count,
                GROUP_CONCAT(el.public_license_key ORDER BY el.public_license_key SEPARATOR ',') AS license_keys,
                GROUP_CONCAT(el.quantity           ORDER BY el.public_license_key SEPARATOR ',') AS license_qtys,
                GROUP_CONCAT(el.usage_count        ORDER BY el.public_license_key SEPARATOR ',') AS license_useds
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

        // Détail Infomaniak par produit, indexé par client_id
        $ikDetailsRaw = $this->db->fetchAll(
            "SELECT cpm4.client_id,
                    ip.service_name,
                    ip.internal_name,
                    ip.customer_name,
                    ip.expired_at,
                    ip.is_trial,
                    ip.is_free
             FROM client_provider_mappings cpm4
             JOIN providers pr ON pr.id = cpm4.provider_id AND pr.code = 'infomaniak'
             JOIN infomaniak_accounts ia
                  ON CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci = cpm4.provider_client_id
                  AND ia.connection_id = cpm4.connection_id
             JOIN infomaniak_products ip
                  ON ip.infomaniak_account_id = ia.infomaniak_account_id
                  AND ip.connection_id = ia.connection_id
             WHERE cpm4.is_confirmed = 1
             ORDER BY cpm4.client_id, ip.service_name, ip.internal_name"
        );

        $ikDetails = [];
        foreach ($ikDetailsRaw as $row) {
            $ikDetails[$row['client_id']][] = $row;
        }

        // Détail NinjaOne : équipements individuels par client et node_group
        $ninjaDetailsRaw = $this->db->fetchAll(
            "SELECT cpm3.client_id, nd.node_group, nd.display_name, nd.dns_name,
                    nd.is_online, nd.last_contact, nd.os_name
             FROM ninjaone_devices nd
             JOIN ninjaone_organizations no2
                  ON no2.ninjaone_org_id = nd.ninjaone_org_id
                  AND no2.connection_id = nd.connection_id
             JOIN client_provider_mappings cpm3
                  ON CAST(no2.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci = cpm3.provider_client_id
                  AND cpm3.connection_id = no2.connection_id
                  AND cpm3.is_confirmed = 1
             JOIN providers pr ON pr.id = cpm3.provider_id AND pr.code = 'ninjaone'
             ORDER BY cpm3.client_id, nd.node_group, nd.display_name"
        );

        $ninjaDevices = [];
        foreach ($ninjaDetailsRaw as $row) {
            $ninjaDevices[$row['client_id']][$row['node_group']][] = $row;
        }

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $this->render('licenses/index', [
            'pageTitle'       => 'Récap Licences',
            'breadcrumbs'     => ['Dashboard' => '/', 'Récap Licences' => null],
            'clients'         => $clients,
            'esetDetails'     => $esetDetails,
            'bcLicDetails'    => $bcLicDetails,
            'bcSubDetails'    => $bcSubDetails,
            'ikDetails'       => $ikDetails,
            'ninjaDevices'    => $ninjaDevices,
            'total'           => $total,
            'page'            => $page,
            'perPage'         => $perPage,
            'search'          => $search,
            'tagIds'          => $tagIds,
            'providerFilters' => $providerFilters,
            'showAll'         => $showAll,
            'tagLogic'        => $tagLogic,
            'providerLogic'   => $providerLogic,
            'allTags'         => $allTags,
            'sortBy'          => $sortBy,
            'sortDir'         => $sortDir,
            // Compat ancien code vue
            'tagId'           => $tagIds[0] ?? 0,
            'providerFilter'  => $providerFilters[0] ?? '',
        ]);
    }

    /**
     * GET /licenses/{id}/report — Génère et stream un rapport PDF pour un client.
     */
    public function report(array $params = []): void
    {
        $clientId = (int)($params['id'] ?? 0);
        if (!$clientId) { http_response_code(404); exit; }

        $client = $this->db->fetchOne(
            "SELECT * FROM clients WHERE id = ? AND is_active = 1",
            [$clientId]
        );
        if (!$client) { http_response_code(404); exit; }

        $tags = $this->db->fetchAll(
            "SELECT t.name, t.color
             FROM client_tags ct JOIN tags t ON t.id = ct.tag_id
             WHERE ct.client_id = ?
             ORDER BY t.display_order ASC, t.name ASC",
            [$clientId]
        );

        $esetDetail = $this->db->fetchAll(
            "SELECT COALESCE(el.product_name, 'Sans produit') AS product_name,
                    el.state,
                    SUM(el.quantity)    AS seats_total,
                    SUM(el.usage_count) AS seats_used,
                    COUNT(el.id)        AS lic_count,
                    GROUP_CONCAT(el.public_license_key ORDER BY el.public_license_key SEPARATOR ', ') AS license_keys,
                    MIN(el.expiration_date) AS expiration_date
             FROM client_provider_mappings cpm
             JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
             JOIN eset_licenses el  ON el.eset_company_id = ec.eset_company_id
             WHERE cpm.client_id = ?
               AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'eset' LIMIT 1)
               AND cpm.is_confirmed = 1
             GROUP BY el.product_name, el.state
             ORDER BY el.product_name",
            [$clientId]
        );

        $bcDetail = $this->db->fetchAll(
            "SELECT bs.offer_name,
                    bs.status,
                    bs.quantity,
                    bs.start_date,
                    bs.end_date,
                    bs.billing_frequency,
                    bs.term_duration,
                    bs.is_trial,
                    bs.auto_renewal,
                    JSON_UNQUOTE(JSON_EXTRACT(bs.raw_data, '$.listPrice.value'))         AS list_price,
                    JSON_UNQUOTE(JSON_EXTRACT(bs.raw_data, '$.listPrice.currency.name')) AS currency
             FROM client_provider_mappings cpm
             JOIN be_cloud_customers bcc
                  ON bcc.be_cloud_customer_id = cpm.provider_client_id
                  AND bcc.connection_id = cpm.connection_id
             JOIN be_cloud_subscriptions bs ON bs.be_cloud_customer_id = bcc.be_cloud_customer_id
             WHERE cpm.client_id = ?
               AND cpm.is_confirmed = 1
               AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'becloud' LIMIT 1)
             ORDER BY bs.offer_name",
            [$clientId]
        );

        $bcLicDetail = $this->db->fetchAll(
            "SELECT bcl.sku_id, bcl.name,
                    bcl.total_licenses, bcl.consumed_licenses,
                    bcl.available_licenses, bcl.suspended_licenses
             FROM client_provider_mappings cpm
             JOIN be_cloud_customers bcc
                  ON bcc.be_cloud_customer_id = cpm.provider_client_id
                  AND bcc.connection_id = cpm.connection_id
             JOIN be_cloud_licenses bcl
                  ON bcl.be_cloud_customer_id = bcc.be_cloud_customer_id
                  AND bcl.connection_id = bcc.connection_id
             WHERE cpm.client_id = ?
               AND cpm.is_confirmed = 1
               AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'becloud' LIMIT 1)
             ORDER BY bcl.name",
            [$clientId]
        );

        $ninjaDetail = $this->db->fetchAll(
            "SELECT no2.name, no2.rmm_count, no2.nms_count, no2.mdm_count,
                    no2.vmm_count, no2.cloud_count
             FROM client_provider_mappings cpm
             JOIN ninjaone_organizations no2
                  ON CAST(no2.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci = cpm.provider_client_id
                  AND no2.connection_id = cpm.connection_id
             WHERE cpm.client_id = ?
               AND cpm.is_confirmed = 1
               AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'ninjaone' LIMIT 1)
             ORDER BY no2.name",
            [$clientId]
        );

        $infomaniakDetail = $this->db->fetchAll(
            "SELECT ia.name AS account_name,
                    ip.service_name,
                    ip.internal_name,
                    ip.customer_name,
                    ip.expired_at,
                    ip.is_trial,
                    ip.is_free
             FROM client_provider_mappings cpm
             JOIN infomaniak_accounts ia
                  ON CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci = cpm.provider_client_id
                  AND ia.connection_id = cpm.connection_id
             JOIN infomaniak_products ip
                  ON ip.infomaniak_account_id = ia.infomaniak_account_id
                  AND ip.connection_id = ia.connection_id
             WHERE cpm.client_id = ?
               AND cpm.is_confirmed = 1
               AND cpm.provider_id = (SELECT id FROM providers WHERE code = 'infomaniak' LIMIT 1)
             ORDER BY ip.service_name, ip.internal_name",
            [$clientId]
        );

        // Rendu du template HTML
        ob_start();
        include APP_ROOT . '/resources/views/licenses/report.php';
        $html = ob_get_clean();

        // Génération PDF via Dompdf
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $slug     = preg_replace('/[^a-z0-9]+/i', '-', $client['client_number'] ?: $client['name']);
        $filename = 'rapport-licences-' . strtolower(trim($slug, '-')) . '-' . date('Y-m-d') . '.pdf';

        $dompdf->stream($filename, ['Attachment' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function buildWhere(string $search, array $tagIds, string $tagLogic = 'or'): array
    {
        $conditions   = [];
        $params       = [];
        $tagHavingSql = '';

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR c.client_number LIKE ?)";
            $like         = '%' . $search . '%';
            $params[]     = $like;
            $params[]     = $like;
        }

        if (!empty($tagIds)) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $conditions[] = "ct.tag_id IN ($placeholders)";
            $params       = array_merge($params, $tagIds);
            // Mode ET : le client doit posséder TOUS les tags sélectionnés
            if ($tagLogic === 'and' && count($tagIds) > 1) {
                $tagHavingSql = 'COUNT(DISTINCT ct.tag_id) = ' . count($tagIds);
            }
        }

        $whereSql = $conditions ? 'WHERE c.is_active = 1 AND ' . implode(' AND ', $conditions) : 'WHERE c.is_active = 1';

        return [$whereSql, $params, $tagHavingSql];
    }

    private function buildHaving(array $providerFilters, bool $showAll, string $providerLogic = 'or', string $tagHavingSql = ''): string
    {
        // Colonnes des derived tables (bc_agg, ninja_agg) doivent être enveloppées dans MAX()
        // pour satisfaire ONLY_FULL_GROUP_BY — équivalent ici car 1 row par client_id.
        $parts = [];

        if (in_array('eset', $providerFilters)) {
            $parts[] = 'COUNT(DISTINCT el.id) > 0';
        }
        if (in_array('becloud', $providerFilters)) {
            $parts[] = 'COALESCE(MAX(bc_agg.bc_sub_count), 0) > 0';
        }
        if (in_array('ninjaone', $providerFilters)) {
            $parts[] = '(COALESCE(MAX(ninja_agg.ninja_rmm), 0) + COALESCE(MAX(ninja_agg.ninja_nms), 0) + COALESCE(MAX(ninja_agg.ninja_mdm), 0)) > 0';
        }
        if (in_array('infomaniak', $providerFilters)) {
            $parts[] = 'COALESCE(MAX(ik_agg.ik_product_count), 0) > 0';
        }

        $havingParts = [];

        if ($tagHavingSql !== '') {
            $havingParts[] = $tagHavingSql;
        }

        if (!empty($parts)) {
            $glue = $providerLogic === 'and' ? ' AND ' : ' OR ';
            $havingParts[] = '(' . implode($glue, $parts) . ')';
        } elseif (!$showAll) {
            $havingParts[] = '(
                COUNT(DISTINCT el.id) > 0
                OR COALESCE(MAX(bc_agg.bc_sub_count), 0) > 0
                OR (COALESCE(MAX(ninja_agg.ninja_rmm), 0) + COALESCE(MAX(ninja_agg.ninja_nms), 0) + COALESCE(MAX(ninja_agg.ninja_mdm), 0)) > 0
                OR COALESCE(MAX(ik_agg.ik_product_count), 0) > 0
            )';
        }

        return $havingParts ? 'HAVING ' . implode(' AND ', $havingParts) : '';
    }

    private function bcJoin(): string
    {
        return "LEFT JOIN (
            SELECT cpm2.client_id,
                   COUNT(DISTINCT bs.id)    AS bc_sub_count,
                   SUM(bs.quantity)          AS bc_seats_total,
                   SUM(bs.assigned_licenses) AS bc_seats_used
            FROM be_cloud_subscriptions bs
            JOIN be_cloud_customers bc ON bc.be_cloud_customer_id = bs.be_cloud_customer_id
            JOIN client_provider_mappings cpm2
                 ON cpm2.connection_id = bc.connection_id
                 AND cpm2.provider_client_id = bc.be_cloud_customer_id
                 AND cpm2.is_confirmed = 1
            GROUP BY cpm2.client_id
        ) bc_agg ON bc_agg.client_id = c.id";
    }

    private function ninjaJoin(): string
    {
        return "LEFT JOIN (
            SELECT cpm3.client_id,
                   SUM(no2.rmm_count)   AS ninja_rmm,
                   SUM(no2.nms_count)   AS ninja_nms,
                   SUM(no2.mdm_count)   AS ninja_mdm,
                   SUM(no2.vmm_count)   AS ninja_vmm,
                   SUM(no2.cloud_count) AS ninja_cloud
            FROM ninjaone_organizations no2
            JOIN client_provider_mappings cpm3
                 ON cpm3.provider_client_id = CAST(no2.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci
                 AND cpm3.connection_id = no2.connection_id
                 AND cpm3.is_confirmed = 1
            GROUP BY cpm3.client_id
        ) ninja_agg ON ninja_agg.client_id = c.id";
    }

    private function ikJoin(): string
    {
        return "LEFT JOIN (
            SELECT cpm4.client_id,
                   COUNT(DISTINCT ip.id)    AS ik_product_count,
                   COUNT(DISTINCT ia.id)    AS ik_account_count
            FROM infomaniak_accounts ia
            JOIN infomaniak_products ip
                 ON ip.infomaniak_account_id = ia.infomaniak_account_id
                 AND ip.connection_id = ia.connection_id
            JOIN client_provider_mappings cpm4
                 ON cpm4.provider_client_id = CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci
                 AND cpm4.connection_id = ia.connection_id
                 AND cpm4.is_confirmed = 1
            GROUP BY cpm4.client_id
        ) ik_agg ON ik_agg.client_id = c.id";
    }

    private function bcLicJoin(): string
    {
        return "LEFT JOIN (
            SELECT cpm2.client_id,
                   COUNT(DISTINCT bcl.id)       AS bc_lic_count,
                   SUM(bcl.total_licenses)       AS bc_lic_total,
                   SUM(bcl.consumed_licenses)    AS bc_lic_consumed
            FROM be_cloud_licenses bcl
            JOIN be_cloud_customers bcc
                 ON bcc.be_cloud_customer_id = bcl.be_cloud_customer_id
                 AND bcc.connection_id = bcl.connection_id
            JOIN client_provider_mappings cpm2
                 ON cpm2.connection_id = bcc.connection_id
                 AND cpm2.provider_client_id = bcc.be_cloud_customer_id
                 AND cpm2.is_confirmed = 1
            GROUP BY cpm2.client_id
        ) bc_lic_agg ON bc_lic_agg.client_id = c.id";
    }
}
