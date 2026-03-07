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
        $confirmed = $_GET['confirmed'] ?? '0';
        $sortBy    = $_GET['sort'] ?? 'entity';
        $sortDir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $_pp       = (int)($_GET['perPage'] ?? 50);
        $perPage   = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
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

        $pid = (int)$providerRow['id'];

        // Construire les parties de requête spécifiques au provider
        if ($provider === 'ninjaone') {
            $entityTable  = 'ninjaone_organizations ent';
            $entityIdExpr = 'CAST(ent.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci';
            $connExpr     = 'ent.connection_id';
            $cpmJoin      = "LEFT JOIN client_provider_mappings cpm
                             ON cpm.provider_client_id = CAST(ent.ninjaone_org_id AS CHAR) COLLATE utf8mb4_general_ci
                             AND cpm.connection_id = ent.connection_id
                             AND cpm.provider_id = $pid";
        } elseif ($provider === 'becloud') {
            $entityTable  = 'be_cloud_customers ent';
            $entityIdExpr = 'ent.be_cloud_customer_id';
            $connExpr     = 'ent.connection_id';
            $cpmJoin      = "LEFT JOIN client_provider_mappings cpm
                             ON cpm.provider_client_id = ent.be_cloud_customer_id
                             AND cpm.connection_id = ent.connection_id
                             AND cpm.provider_id = $pid";
        } else {
            $entityTable  = 'eset_companies ent';
            $entityIdExpr = 'ent.eset_company_id';
            $connExpr     = 'NULL';
            $cpmJoin      = "LEFT JOIN client_provider_mappings cpm
                             ON cpm.provider_client_id = ent.eset_company_id
                             AND cpm.provider_id = $pid";
        }

        $allowedSorts = [
            'entity'    => 'ent.name',
            'client'    => 'c.name',
            'method'    => 'cpm.mapping_method',
            'score'     => 'cpm.match_score',
            'confirmed' => 'cpm.is_confirmed',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'ent.name';

        $conditions  = [];
        $queryParams = [];

        if ($search !== '') {
            $conditions[] = "(ent.name LIKE ? OR c.name LIKE ? OR c.client_number LIKE ?)";
            $like = '%' . $search . '%';
            array_push($queryParams, $like, $like, $like);
        }

        if ($confirmed === '0') {
            $conditions[] = "(cpm.id IS NULL OR cpm.is_confirmed = 0)";
        } elseif ($confirmed === '1') {
            $conditions[] = "cpm.is_confirmed = 1";
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = $this->db->count(
            "SELECT COUNT(*)
             FROM $entityTable
             $cpmJoin
             LEFT JOIN clients c ON c.id = cpm.client_id
             $whereSql",
            $queryParams
        );

        $entities = $this->db->fetchAll(
            "SELECT
                $entityIdExpr  AS provider_client_id,
                ent.name       AS provider_name,
                $connExpr      AS connection_id,
                cpm.id         AS mapping_id,
                cpm.client_id,
                cpm.is_confirmed,
                cpm.match_score,
                cpm.mapping_method,
                cpm.notes,
                c.name         AS client_name,
                c.client_number
             FROM $entityTable
             $cpmJoin
             LEFT JOIN clients c ON c.id = cpm.client_id
             $whereSql
             ORDER BY $orderCol $sortDir, ent.name ASC
             LIMIT $perPage OFFSET $offset",
            $queryParams
        );

        $clients = $this->db->fetchAll(
            "SELECT id, name, client_number FROM clients WHERE is_active = 1 ORDER BY name"
        );

        $autoConfirmPreview = [];
        foreach ([100, 95, 90, 85, 80, 70] as $t) {
            $autoConfirmPreview[$t] = $this->db->count(
                "SELECT COUNT(*) FROM client_provider_mappings
                 WHERE provider_id = ? AND is_confirmed = 0 AND match_score >= ?",
                [$pid, $t]
            );
        }

        $this->render('clients/mapping', [
            'pageTitle'          => 'Mapping fournisseurs',
            'breadcrumbs'        => ['Dashboard' => '/', 'Mapping' => null],
            'provider'           => $provider,
            'providerRow'        => $providerRow,
            'entities'           => $entities,
            'clients'            => $clients,
            'total'              => $total,
            'page'               => $page,
            'perPage'            => $perPage,
            'search'             => $search,
            'confirmed'          => $confirmed,
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

        // Company name + connection_id selon le provider
        $companyName  = null;
        $connectionId = null;

        if ($providerCode === 'eset') {
            $entity      = $this->db->fetchOne(
                "SELECT name FROM eset_companies WHERE eset_company_id = ? LIMIT 1",
                [$providerClientId]
            );
            $companyName = $entity['name'] ?? null;
        } elseif ($providerCode === 'becloud') {
            $entity       = $this->db->fetchOne(
                "SELECT name, connection_id FROM be_cloud_customers WHERE be_cloud_customer_id = ? LIMIT 1",
                [$providerClientId]
            );
            $companyName  = $entity['name'] ?? null;
            $connectionId = $entity['connection_id'] ?? null;
        } elseif ($providerCode === 'ninjaone') {
            $entity       = $this->db->fetchOne(
                "SELECT name, connection_id FROM ninjaone_organizations WHERE ninjaone_org_id = ? LIMIT 1",
                [(int)$providerClientId]
            );
            $companyName  = $entity['name'] ?? null;
            $connectionId = $entity['connection_id'] ?? null;
        }

        $this->db->execute(
            "INSERT INTO client_provider_mappings
                (client_id, provider_id, connection_id, provider_client_id, provider_client_name,
                 mapping_method, is_confirmed, notes)
             VALUES (?, ?, ?, ?, ?, 'manual', 1, ?)
             ON DUPLICATE KEY UPDATE
                client_id            = VALUES(client_id),
                connection_id        = VALUES(connection_id),
                provider_client_name = VALUES(provider_client_name),
                mapping_method       = 'manual',
                is_confirmed         = 1,
                notes                = VALUES(notes),
                updated_at           = NOW()",
            [$clientId, (int)$provider['id'], $connectionId, $providerClientId, $companyName, $notes ?: null]
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
