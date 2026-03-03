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
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 50;
        $offset    = ($page - 1) * $perPage;

        $providerRow = $this->db->fetchOne(
            "SELECT id, name FROM providers WHERE code = ? LIMIT 1",
            [$provider]
        );

        if (!$providerRow) {
            $this->flash('danger', "Fournisseur inconnu : $provider");
            $this->redirect('/mapping');
            return;
        }

        $conditions = ["cpm.provider_id = ?"];
        $queryParams = [(int)$providerRow['id']];

        if ($search !== '') {
            $conditions[] = "(ec.name LIKE ? OR c.name LIKE ? OR c.client_number LIKE ?)";
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
             JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
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
                c.client_number,
                s.code AS structure_code
             FROM client_provider_mappings cpm
             JOIN eset_companies ec ON ec.eset_company_id = cpm.provider_client_id
             JOIN clients c ON c.id = cpm.client_id
             JOIN structures s ON s.id = c.structure_id
             $whereSql
             ORDER BY cpm.is_confirmed ASC, cpm.match_score DESC, ec.name ASC
             LIMIT $perPage OFFSET $offset",
            $queryParams
        );

        // Companies sans mapping pour permettre la liaison manuelle
        $unmapped = $this->db->fetchAll(
            "SELECT ec.eset_company_id, ec.name, ec.custom_identifier
             FROM eset_companies ec
             WHERE ec.eset_company_id NOT IN (
                 SELECT provider_client_id FROM client_provider_mappings
                 WHERE provider_id = ?
             )
             ORDER BY ec.name
             LIMIT 200",
            [(int)$providerRow['id']]
        );

        $clients = $this->db->fetchAll(
            "SELECT c.id, c.name, c.client_number, s.code AS structure_code
             FROM clients c
             JOIN structures s ON s.id = c.structure_id
             WHERE c.is_active = 1
             ORDER BY s.code, c.name"
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
