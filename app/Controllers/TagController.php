<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class TagController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /tags — Liste + gestion des tags
     */
    public function index(array $params = []): void
    {
        $tags = $this->db->fetchAll(
            "SELECT t.*, COUNT(ct.client_id) AS client_count
             FROM tags t
             LEFT JOIN client_tags ct ON ct.tag_id = t.id
             GROUP BY t.id
             ORDER BY t.display_order ASC, t.name ASC"
        );

        $this->render('tags/index', [
            'pageTitle'   => 'Tags clients',
            'breadcrumbs' => ['Dashboard' => '/', 'Tags' => null],
            'tags'        => $tags,
        ]);
    }

    /**
     * POST /tags/create — Créer un tag (AJAX JSON)
     */
    public function create(array $params = []): void
    {
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');

        if ($name === '') {
            $this->json(['success' => false, 'message' => 'Le nom est requis.'], 422);
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6c757d';
        }

        // Ordre = max actuel + 1
        $maxOrder = $this->db->fetchOne("SELECT MAX(display_order) AS m FROM tags");
        $order    = ((int)($maxOrder['m'] ?? 0)) + 1;

        try {
            $this->db->execute(
                "INSERT INTO tags (name, color, display_order) VALUES (?, ?, ?)",
                [$name, $color, $order]
            );
            $id  = (int)$this->db->lastInsertId();
            $tag = $this->db->fetchOne("SELECT * FROM tags WHERE id = ?", [$id]);
            $this->json(['success' => true, 'tag' => $tag]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $this->json(['success' => false, 'message' => "Un tag « {$name} » existe déjà."], 422);
            } else {
                $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * POST /tags/update — Modifier nom/couleur d'un tag (AJAX JSON)
     */
    public function update(array $params = []): void
    {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '');

        if ($id <= 0 || $name === '') {
            $this->json(['success' => false, 'message' => 'Paramètres invalides.'], 422);
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6c757d';
        }

        try {
            $this->db->execute(
                "UPDATE tags SET name = ?, color = ? WHERE id = ?",
                [$name, $color, $id]
            );
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $this->json(['success' => false, 'message' => "Un tag « {$name} » existe déjà."], 422);
            } else {
                $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * POST /tags/delete — Supprimer un tag (AJAX JSON)
     */
    public function delete(array $params = []): void
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['success' => false, 'message' => 'ID invalide.'], 422);
            return;
        }

        $this->db->execute("DELETE FROM tags WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /**
     * POST /tags/reorder — Mettre à jour l'ordre d'affichage (AJAX JSON)
     * Body: ids=1,3,2 (ordre souhaité, virgule-séparé)
     */
    public function reorder(array $params = []): void
    {
        $idsRaw = $_POST['ids'] ?? '';
        $ids    = array_filter(array_map('intval', explode(',', $idsRaw)));

        if (empty($ids)) {
            $this->json(['success' => false, 'message' => 'Liste IDs vide.'], 422);
            return;
        }

        foreach ($ids as $order => $id) {
            $this->db->execute(
                "UPDATE tags SET display_order = ? WHERE id = ?",
                [$order, $id]
            );
        }

        $this->json(['success' => true]);
    }
}
