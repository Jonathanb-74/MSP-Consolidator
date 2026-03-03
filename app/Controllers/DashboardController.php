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
        // Stats globales
        $totalClients = $this->db->count("SELECT COUNT(*) FROM clients WHERE is_active = 1");

        $structureStats = $this->db->fetchAll(
            "SELECT s.code, COUNT(c.id) AS total
             FROM structures s
             LEFT JOIN clients c ON c.structure_id = s.id AND c.is_active = 1
             GROUP BY s.id, s.code
             ORDER BY s.code"
        );

        // Stats ESET
        $esetStats = $this->db->fetchOne(
            "SELECT
                COUNT(DISTINCT ec.id)     AS total_companies,
                COUNT(el.id)              AS total_licenses,
                SUM(el.quantity)          AS total_seats,
                SUM(el.usage_count)       AS used_seats,
                SUM(CASE WHEN el.state = 'EXPIRED' THEN 1 ELSE 0 END) AS expired_licenses,
                SUM(CASE WHEN el.expiration_date BETWEEN CURDATE()
                    AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND el.state != 'EXPIRED' THEN 1 ELSE 0 END) AS expiring_soon
             FROM eset_companies ec
             LEFT JOIN eset_licenses el ON el.eset_company_id = ec.eset_company_id"
        );

        // Providers avec statut sync
        $providers = $this->db->fetchAll(
            "SELECT p.*, sl.status AS last_sync_status, sl.finished_at AS last_sync_at
             FROM providers p
             LEFT JOIN sync_logs sl ON sl.id = (
                 SELECT id FROM sync_logs
                 WHERE provider_id = p.id
                 ORDER BY started_at DESC LIMIT 1
             )
             ORDER BY p.code"
        );

        // Mappings non confirmés
        $pendingMappings = $this->db->count(
            "SELECT COUNT(*) FROM client_provider_mappings WHERE is_confirmed = 0"
        );

        $this->render('dashboard/index', [
            'pageTitle'       => 'Dashboard',
            'breadcrumbs'     => ['Dashboard' => null],
            'totalClients'    => $totalClients,
            'structureStats'  => $structureStats,
            'esetStats'       => $esetStats,
            'providers'       => $providers,
            'pendingMappings' => $pendingMappings,
        ]);
    }
}
