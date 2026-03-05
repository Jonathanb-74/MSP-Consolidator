<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class MappingController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /mapping — Interface de mapping fournisseur ↔ client
     */
    public function index(array $params = []): void
    {
        $provider  = $_GET['provider'] ?? 'eset';
        $search    = trim($_GET['search'] ?? '');
        $confirmed = $_GET['confirmed'] ?? '';  // '', '0', '1'
        $minScore  = ($_GET['min_score'] ?? '') !== '' ? (int)$_GET['min_score'] : null;
        $sortBy    = $_GET['sort'] ?? 'confirmed';
        $sortDir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $_pp     = (int)($_GET['perPage'] ?? 50);
        $perPage = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset    = ($page - 1) * $perPage;

        $allowedSorts = [
            'company'   => 'prov_tbl.name',
            'client'    => 'c.name',
            'method'    => 'cpm.mapping_method',
            'score'     => 'cpm.match_score',
            'confirmed' => 'cpm.is_confirmed',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'cpm.is_confirmed';

        $providerRow = $this->db->fetchOne(
            "SELECT id, name FROM providers WHERE code = ? LIMIT 1",
            [$provider]
        );

        if (!$providerRow) {
            $this->flash('danger', "Fournisseur inconnu : $provider");
            $this->redirect('/mapping');
            return;
        }

        // Table et colonne ID selon le provider
        if ($provider === 'becloud') {
            $provJoin       = "JOIN be_cloud_customers prov_tbl ON prov_tbl.be_cloud_customer_id = cpm.provider_client_id";
            $unmappedSelect = "SELECT prov_tbl.be_cloud_customer_id AS provider_client_id, prov_tbl.name, prov_tbl.internal_identifier AS custom_identifier FROM be_cloud_customers prov_tbl";
            $unmappedCond   = "prov_tbl.be_cloud_customer_id NOT IN (SELECT provider_client_id FROM client_provider_mappings WHERE provider_id = ?)";
        } else {
            $provJoin       = "JOIN eset_companies prov_tbl ON prov_tbl.eset_company_id = cpm.provider_client_id";
            $unmappedSelect = "SELECT prov_tbl.eset_company_id AS provider_client_id, prov_tbl.name, prov_tbl.custom_identifier FROM eset_companies prov_tbl";
            $unmappedCond   = "prov_tbl.eset_company_id NOT IN (SELECT provider_client_id FROM client_provider_mappings WHERE provider_id = ?)";
        }

        $conditions = ["cpm.provider_id = ?"];
        $queryParams = [(int)$providerRow['id']];

        if ($search !== '') {
            $conditions[] = "(prov_tbl.name LIKE ? OR c.name LIKE ? OR c.client_number LIKE ?)";
            $like = '%' . $search . '%';
            array_push($queryParams, $like, $like, $like);
        }

        if ($confirmed === '0') {
            $conditions[] = "cpm.is_confirmed = 0";
        } elseif ($confirmed === '1') {
            $conditions[] = "cpm.is_confirmed = 1";
        }

        if ($minScore !== null) {
            $conditions[] = "cpm.match_score >= ?";
            $queryParams[] = $minScore;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $total = $this->db->count(
            "SELECT COUNT(*)
             FROM client_provider_mappings cpm
             $provJoin
             JOIN clients c ON c.id = cpm.client_id
             $whereSql",
            $queryParams
        );

        $mappings = $this->db->fetchAll(
            "SELECT
                cpm.id,
                cpm.provider_client_id,
                cpm.provider_client_name,
                cpm.mapping_method,
                cpm.is_confirmed,
                cpm.match_score,
                cpm.notes,
                c.id AS client_id,
                c.name AS client_name,
                c.client_number
             FROM client_provider_mappings cpm
             $provJoin
             JOIN clients c ON c.id = cpm.client_id
             $whereSql
             ORDER BY $orderCol $sortDir, prov_tbl.name ASC
             LIMIT $perPage OFFSET $offset",
            $queryParams
        );

        // Entrées sans mapping pour permettre la liaison manuelle
        $unmapped = $this->db->fetchAll(
            "$unmappedSelect WHERE $unmappedCond ORDER BY prov_tbl.name LIMIT 200",
            [(int)$providerRow['id']]
        );

        $clients = $this->db->fetchAll(
            "SELECT c.id, c.name, c.client_number
             FROM clients c
             WHERE c.is_active = 1
             ORDER BY c.name"
        );

        // Comptage des mappings non confirmés avec score >= seuil (pour la preview auto-confirm)
        $autoConfirmPreview = [];
        foreach ([100, 95, 90, 85, 80, 70] as $t) {
            $autoConfirmPreview[$t] = $this->db->count(
                "SELECT COUNT(*) FROM client_provider_mappings
                 WHERE provider_id = ? AND is_confirmed = 0 AND match_score >= ?",
                [(int)$providerRow['id'], $t]
            );
        }

        $this->render('clients/mapping', [
            'pageTitle'          => 'Mapping fournisseurs',
            'breadcrumbs'        => ['Dashboard' => '/', 'Mapping' => null],
            'provider'           => $provider,
            'providerRow'        => $providerRow,
            'mappings'           => $mappings,
            'unmapped'           => $unmapped,
            'clients'            => $clients,
            'total'              => $total,
            'page'               => $page,
            'perPage'            => $perPage,
            'search'             => $search,
            'confirmed'          => $confirmed,
            'minScore'           => $minScore,
            'autoConfirmPreview' => $autoConfirmPreview,
            'sortBy'             => $sortBy,
            'sortDir'            => $sortDir,
        ]);
    }

    /**
     * POST /mapping/link — Créer ou confirmer un mapping
     */
    public function link(array $params = []): void
    {
        $clientId         = (int)($_POST['client_id'] ?? 0);
        $providerClientId = trim($_POST['provider_client_id'] ?? '');
        $providerCode     = trim($_POST['provider'] ?? 'eset');
        $notes            = trim($_POST['notes'] ?? '');

        if (!$clientId || !$providerClientId) {
            $this->json(['success' => false, 'message' => 'Données manquantes.'], 400);
            return;
        }

        $provider = $this->db->fetchOne(
            "SELECT id FROM providers WHERE code = ? LIMIT 1",
            [$providerCode]
        );

        if (!$provider) {
            $this->json(['success' => false, 'message' => 'Fournisseur inconnu.'], 400);
            return;
        }

        // Company name pour le mapping
        $companyName = null;
        if ($providerCode === 'eset') {
            $company = $this->db->fetchOne(
                "SELECT name FROM eset_companies WHERE eset_company_id = ? LIMIT 1",
                [$providerClientId]
            );
            $companyName = $company['name'] ?? null;
        } elseif ($providerCode === 'becloud') {
            $company = $this->db->fetchOne(
                "SELECT name FROM be_cloud_customers WHERE be_cloud_customer_id = ? LIMIT 1",
                [$providerClientId]
            );
            $companyName = $company['name'] ?? null;
        }

        $this->db->execute(
            "INSERT INTO client_provider_mappings
                (client_id, provider_id, provider_client_id, provider_client_name,
                 mapping_method, is_confirmed, notes)
             VALUES (?, ?, ?, ?, 'manual', 1, ?)
             ON DUPLICATE KEY UPDATE
                client_id            = VALUES(client_id),
                provider_client_name = VALUES(provider_client_name),
                mapping_method       = 'manual',
                is_confirmed         = 1,
                notes                = VALUES(notes),
                updated_at           = NOW()",
            [$clientId, (int)$provider['id'], $providerClientId, $companyName, $notes ?: null]
        );

        $this->json(['success' => true, 'message' => 'Mapping enregistré.']);
    }

    /**
     * POST /mapping/unlink — Supprimer un mapping
     */
    public function unlink(array $params = []): void
    {
        $mappingId = (int)($_POST['mapping_id'] ?? 0);

        if (!$mappingId) {
            $this->json(['success' => false, 'message' => 'ID mapping manquant.'], 400);
            return;
        }

        $this->db->execute(
            "DELETE FROM client_provider_mappings WHERE id = ?",
            [$mappingId]
        );

        $this->json(['success' => true, 'message' => 'Mapping supprimé.']);
    }

    /**
     * POST /mapping/confirm-bulk — Confirmer une sélection de mappings par ID
     */
    public function confirmBulk(array $params = []): void
    {
        $raw = $_POST['mapping_ids'] ?? '';
        $ids = array_values(array_filter(array_map('intval', explode(',', $raw))));

        if (empty($ids)) {
            $this->json(['success' => false, 'message' => 'Aucun mapping sélectionné.'], 400);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->execute(
            "UPDATE client_provider_mappings
             SET is_confirmed = 1, updated_at = NOW()
             WHERE id IN ($placeholders)",
            $ids
        );

        $this->json([
            'success' => true,
            'message' => count($ids) . ' mapping(s) confirmé(s).',
            'count'   => count($ids),
        ]);
    }

    /**
     * POST /mapping/auto-confirm — Confirmer tous les mappings non confirmés >= seuil de score
     */
    public function autoConfirm(array $params = []): void
    {
        $threshold    = max(0, min(100, (int)($_POST['threshold'] ?? 80)));
        $providerCode = trim($_POST['provider'] ?? 'eset');

        $provider = $this->db->fetchOne(
            "SELECT id FROM providers WHERE code = ? LIMIT 1",
            [$providerCode]
        );

        if (!$provider) {
            $this->json(['success' => false, 'message' => 'Fournisseur inconnu.'], 400);
            return;
        }

        $stmt = $this->db->execute(
            "UPDATE client_provider_mappings
             SET is_confirmed = 1, updated_at = NOW()
             WHERE provider_id = ? AND is_confirmed = 0 AND match_score >= ?",
            [(int)$provider['id'], $threshold]
        );

        $count = $stmt->rowCount();
        $this->json([
            'success' => true,
            'message' => "{$count} mapping(s) confirmé(s) automatiquement (score ≥ {$threshold}%).",
            'count'   => $count,
        ]);
    }
}
