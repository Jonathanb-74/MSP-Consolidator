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

        // Stats Be-Cloud
        $bcStats = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT bcc.be_cloud_customer_id)                                AS total_customers,
                COUNT(bs.id)                                                            AS total_subscriptions,
                SUM(CASE WHEN bs.status = 'Active'          THEN 1 ELSE 0 END)         AS active_subscriptions,
                COALESCE(SUM(bs.quantity), 0)                                          AS total_seats,
                COALESCE(SUM(bs.assigned_licenses), 0)                                 AS assigned_seats
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

        // Providers avec statut dernière sync (par connexion)
        // last_sync_at : prendre le max entre provider_connections et sync_logs (fallback si champ null)
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

        // Mappings non confirmés
        $pendingMappings = $this->db->count(
            "SELECT COUNT(*) FROM client_provider_mappings WHERE is_confirmed = 0"
        );

        $this->render('dashboard/index', [
            'pageTitle'          => 'Dashboard',
            'breadcrumbs'        => ['Dashboard' => null],
            'totalClients'       => $totalClients,
            'tagStats'           => $tagStats,
            'esetStats'          => $esetStats,
            'bcStats'            => $bcStats,
            'ninjaStats'         => $ninjaStats,
            'providerConnections' => $providerConnections,
            'pendingMappings'    => $pendingMappings,
        ]);
    }
}
