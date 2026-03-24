<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class DashboardController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(array $params = []): void
    {
        $totalClients = $this->db->count("SELECT COUNT(*) FROM clients WHERE is_active = 1");

        $tagStats = $this->db->fetchAll(
            "SELECT t.name, t.color, COUNT(ct.client_id) AS total
             FROM tags t
             LEFT JOIN client_tags ct ON ct.tag_id = t.id
             GROUP BY t.id
             ORDER BY t.display_order ASC, t.name ASC"
        );

        // Stats ESET — états numériques : 1=Normal, 3=Suspendu, 4=Complet, 0/2=Problème
        $esetStats = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT ec.id)                                                   AS total_companies,
                COUNT(el.id)                                                            AS total_licenses,
                COALESCE(SUM(el.quantity), 0)                                          AS total_seats,
                COALESCE(SUM(el.usage_count), 0)                                       AS used_seats,
                SUM(CASE WHEN el.state IN ('1','VALID')             THEN 1 ELSE 0 END) AS normal_licenses,
                SUM(CASE WHEN el.state = '4'                        THEN 1 ELSE 0 END) AS full_licenses,
                SUM(CASE WHEN el.state IN ('3','SUSPENDED')         THEN 1 ELSE 0 END) AS suspended_licenses,
                SUM(CASE WHEN el.state IN ('0','2','EXPIRED')       THEN 1 ELSE 0 END) AS problem_licenses
             FROM eset_companies ec
             LEFT JOIN eset_licenses el ON el.eset_company_id = ec.eset_company_id"
        );

        // Stats Be-Cloud — abonnements + licences M365 réelles (be_cloud_licenses)
        $bcStats = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT bcc.be_cloud_customer_id)                                        AS total_customers,
                COUNT(bs.id)                                                                    AS total_subscriptions,
                SUM(CASE WHEN bs.status = 'Active'                              THEN 1 ELSE 0 END) AS active_subscriptions,
                SUM(CASE WHEN bs.end_date BETWEEN CURDATE()
                         AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)               THEN 1 ELSE 0 END) AS sub_expiring_30d,
                COALESCE((SELECT SUM(total_licenses)     FROM be_cloud_licenses), 0)            AS lic_total,
                COALESCE((SELECT SUM(consumed_licenses)  FROM be_cloud_licenses), 0)            AS lic_consumed,
                COALESCE((SELECT SUM(available_licenses) FROM be_cloud_licenses), 0)            AS lic_available
             FROM be_cloud_customers bcc
             LEFT JOIN be_cloud_subscriptions bs ON bs.be_cloud_customer_id = bcc.be_cloud_customer_id"
        );

        // Stats NinjaOne
        $ninjaStats = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT no2.ninjaone_org_id)        AS total_orgs,
                COALESCE(SUM(no2.rmm_count), 0)            AS rmm_total,
                COALESCE(SUM(no2.nms_count), 0)            AS nms_total,
                COALESCE(SUM(no2.mdm_count), 0)            AS mdm_total,
                (SELECT COUNT(*) FROM ninjaone_devices WHERE is_online = 1) AS devices_online,
                (SELECT COUNT(*) FROM ninjaone_devices WHERE is_online = 0) AS devices_offline
             FROM ninjaone_organizations no2"
        );

        // Stats Infomaniak
        $infoStats = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT ia.id)                                                                          AS total_accounts,
                COUNT(ip.id)                                                                                   AS total_products,
                COALESCE(SUM(CASE WHEN ip.expired_at < UNIX_TIMESTAMP()                     THEN 1 ELSE 0 END), 0) AS expired_products,
                COALESCE(SUM(CASE WHEN ip.expired_at BETWEEN UNIX_TIMESTAMP()
                                  AND UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 30 DAY))      THEN 1 ELSE 0 END), 0) AS expiring_30d,
                COALESCE(SUM(CASE WHEN ip.expired_at >= UNIX_TIMESTAMP()
                                  OR ip.expired_at IS NULL                                  THEN 1 ELSE 0 END), 0) AS active_products
             FROM infomaniak_accounts ia
             LEFT JOIN infomaniak_products ip
                 ON ip.infomaniak_account_id = ia.infomaniak_account_id
                AND ip.connection_id         = ia.connection_id"
        );

        // Prochaines expirations — 5 prochaines dans les 30j (Be-Cloud + Infomaniak)
        $upcomingExpirations = $this->db->fetchAll(
            "SELECT provider, expiry_date, item_name, client_name FROM (
                SELECT
                    'becloud'                                    AS provider,
                    bs.end_date                                  AS expiry_date,
                    COALESCE(bs.offer_name, bs.subscription_name, '—') AS item_name,
                    COALESCE(c.name, bcc.name)                  AS client_name
                FROM be_cloud_subscriptions bs
                JOIN be_cloud_customers bcc ON bcc.be_cloud_customer_id = bs.be_cloud_customer_id
                LEFT JOIN client_provider_mappings cpm
                    ON cpm.provider_client_id = bcc.be_cloud_customer_id
                   AND cpm.connection_id      = bcc.connection_id
                   AND cpm.is_confirmed       = 1
                LEFT JOIN clients c ON c.id = cpm.client_id
                WHERE bs.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)

                UNION ALL

                SELECT
                    'infomaniak'                                 AS provider,
                    DATE(FROM_UNIXTIME(ip.expired_at))           AS expiry_date,
                    COALESCE(ip.internal_name, ip.customer_name, ip.service_name, '—') AS item_name,
                    COALESCE(c.name, ia.name)                    AS client_name
                FROM infomaniak_products ip
                JOIN infomaniak_accounts ia
                    ON ia.infomaniak_account_id = ip.infomaniak_account_id
                   AND ia.connection_id         = ip.connection_id
                LEFT JOIN client_provider_mappings cpm
                    ON cpm.provider_client_id = CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_unicode_ci
                   AND cpm.connection_id      = ia.connection_id
                   AND cpm.is_confirmed       = 1
                LEFT JOIN clients c ON c.id = cpm.client_id
                WHERE ip.expired_at BETWEEN UNIX_TIMESTAMP(CURDATE())
                                        AND UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY))
            ) exp
            ORDER BY expiry_date ASC
            LIMIT 5"
        );

        // Compteur total expirations dans 30j (pour badge alerte)
        $expiringCount = $this->db->count(
            "SELECT COUNT(*) FROM (
                SELECT bs.id FROM be_cloud_subscriptions bs
                WHERE bs.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                UNION ALL
                SELECT ip.id FROM infomaniak_products ip
                WHERE ip.expired_at BETWEEN UNIX_TIMESTAMP(CURDATE())
                                        AND UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY))
            ) t"
        );

        // Providers avec statut dernière sync (par connexion)
        $providerConnections = $this->db->fetchAll(
            "SELECT pc.id, pc.name AS connection_name, pc.is_enabled,
                    pc.sync_status, pc.last_sync_at AS pc_last_sync_at,
                    sl.status AS sl_status, sl.finished_at AS sl_last_sync_at,
                    p.code AS provider_code, p.name AS provider_name
             FROM provider_connections pc
             JOIN providers p ON p.id = pc.provider_id
             LEFT JOIN sync_logs sl ON sl.id = (
                 SELECT id FROM sync_logs
                 WHERE connection_id = pc.id
                 ORDER BY started_at DESC LIMIT 1
             )
             WHERE pc.is_enabled = 1
             ORDER BY p.code, pc.name"
        );

        // Nb de connexions en erreur de sync
        $errorConnsCount = count(array_filter($providerConnections, fn($c) =>
            ($c['sync_status'] === 'error') || ($c['sync_status'] === 'idle' && ($c['sl_status'] ?? '') === 'error')
        ));

        // Mappings non confirmés
        $pendingMappings = $this->db->count(
            "SELECT COUNT(*) FROM client_provider_mappings WHERE is_confirmed = 0"
        );

        $this->render('dashboard/index', [
            'pageTitle'           => 'Dashboard',
            'breadcrumbs'         => ['Dashboard' => null],
            'totalClients'        => $totalClients,
            'tagStats'            => $tagStats,
            'esetStats'           => $esetStats,
            'bcStats'             => $bcStats,
            'ninjaStats'          => $ninjaStats,
            'infoStats'           => $infoStats,
            'upcomingExpirations' => $upcomingExpirations,
            'expiringCount'       => $expiringCount,
            'providerConnections' => $providerConnections,
            'errorConnsCount'     => $errorConnsCount,
            'pendingMappings'     => $pendingMappings,
        ]);
    }
}
