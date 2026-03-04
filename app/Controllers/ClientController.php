<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ClientController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /clients — Liste des clients avec filtres et tags
     */
    public function index(array $params = []): void
    {
        $search  = trim($_GET['search'] ?? '');
        $tagId   = (int)($_GET['tag'] ?? 0);
        $sortBy  = $_GET['sort'] ?? 'name';
        $sortDir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $_pp     = (int)($_GET['perPage'] ?? 50);
        $perPage = in_array($_pp, [25, 50, 100, 250]) ? $_pp : 50;
        $offset  = ($page - 1) * $perPage;

        $allowedSorts = [
            'client_number'  => 'c.client_number',
            'name'           => 'c.name',
            'email'          => 'c.email',
            'providers'      => 'provider_count',
            'status'         => 'c.is_active',
        ];
        $orderCol = $allowedSorts[$sortBy] ?? 'c.name';

        [$whereSql, $whereParams] = $this->buildWhere($search, $tagId);

        $total = $this->db->count(
            "SELECT COUNT(DISTINCT c.id) FROM clients c
             LEFT JOIN client_tags ct ON ct.client_id = c.id
             $whereSql",
            $whereParams
        );

        $clients = $this->db->fetchAll(
            "SELECT c.*,
                (SELECT COUNT(*) FROM client_provider_mappings WHERE client_id = c.id) AS provider_count,
                (SELECT GROUP_CONCAT(CONCAT(t2.id, ':', t2.name, ':', t2.color)
                                     ORDER BY t2.display_order ASC SEPARATOR ';;')
                 FROM client_tags ct2 JOIN tags t2 ON t2.id = ct2.tag_id
                 WHERE ct2.client_id = c.id) AS tags_raw
             FROM clients c
             LEFT JOIN client_tags ct ON ct.client_id = c.id
             $whereSql
             GROUP BY c.id
             ORDER BY $orderCol $sortDir
             LIMIT $perPage OFFSET $offset",
            $whereParams
        );

        $allTags = $this->db->fetchAll(
            "SELECT * FROM tags ORDER BY display_order ASC, name ASC"
        );

        $this->render('clients/index', [
            'pageTitle'   => 'Clients',
            'breadcrumbs' => ['Dashboard' => '/', 'Clients' => null],
            'clients'     => $clients,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'search'      => $search,
            'tagId'       => $tagId,
            'allTags'     => $allTags,
            'sortBy'      => $sortBy,
            'sortDir'     => $sortDir,
        ]);
    }

    /**
     * GET /clients/import — Formulaire d'import Excel (global, sans structure)
     */
    public function importForm(array $params = []): void
    {
        $this->render('clients/import', [
            'pageTitle'   => 'Importer des clients',
            'breadcrumbs' => ['Dashboard' => '/', 'Clients' => '/clients', 'Import Excel' => null],
        ]);
    }

    /**
     * POST /clients/import — Traitement de l'import Excel
     */
    public function importProcess(array $params = []): void
    {
        if (empty($_FILES['excel_file']['tmp_name']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Aucun fichier reçu ou erreur upload.');
            $this->redirect('/clients/import');
            return;
        }

        $file    = $_FILES['excel_file'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['xlsx', 'xls', 'csv'];

        if (!in_array($ext, $allowed, true)) {
            $this->flash('danger', 'Format non supporté. Utilisez .xlsx, .xls ou .csv.');
            $this->redirect('/clients/import');
            return;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $uploadDir = $appConfig['uploads_path'];
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }
        $tmpPath = $uploadDir . '/' . uniqid('import_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $tmpPath);

        try {
            $result = $this->processExcelFile($tmpPath);
            @unlink($tmpPath);

            $this->flash(
                'success',
                sprintf(
                    'Import terminé : %d créé(s), %d mis à jour, %d ignoré(s) (code non numérique), %d erreur(s).',
                    $result['created'],
                    $result['updated'],
                    $result['skipped'],
                    count($result['errors'])
                )
            );

            if (!empty($result['errors'])) {
                foreach (array_slice($result['errors'], 0, 10) as $err) {
                    $this->flash('warning', $err);
                }
            }
        } catch (Throwable $e) {
            @unlink($tmpPath);
            $this->flash('danger', 'Erreur lors de l\'import : ' . $e->getMessage());
        }

        $this->redirect('/clients');
    }

    /**
     * POST /clients/tag — Ajouter ou retirer un tag d'un client (AJAX JSON)
     */
    public function toggleTag(array $params = []): void
    {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $tagId    = (int)($_POST['tag_id'] ?? 0);

        if ($clientId <= 0 || $tagId <= 0) {
            $this->json(['success' => false, 'message' => 'Paramètres invalides.'], 422);
            return;
        }

        $client = $this->db->fetchOne("SELECT id FROM clients WHERE id = ? LIMIT 1", [$clientId]);
        $tag    = $this->db->fetchOne("SELECT id FROM tags WHERE id = ? LIMIT 1", [$tagId]);

        if (!$client || !$tag) {
            $this->json(['success' => false, 'message' => 'Client ou tag introuvable.'], 404);
            return;
        }

        $existing = $this->db->fetchOne(
            "SELECT 1 FROM client_tags WHERE client_id = ? AND tag_id = ? LIMIT 1",
            [$clientId, $tagId]
        );

        if ($existing) {
            $this->db->execute(
                "DELETE FROM client_tags WHERE client_id = ? AND tag_id = ?",
                [$clientId, $tagId]
            );
            $action = 'removed';
        } else {
            $this->db->execute(
                "INSERT INTO client_tags (client_id, tag_id) VALUES (?, ?)",
                [$clientId, $tagId]
            );
            $action = 'added';
        }

        $this->json(['success' => true, 'action' => $action]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function processExcelFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            throw new \RuntimeException("Le fichier est vide.");
        }

        // Première ligne = entêtes (normalisés : minuscules + sans accents)
        $rawHeaders = array_shift($rows);
        $colMap     = [];
        foreach ($rawHeaders as $col => $header) {
            $normalized = $this->normalizeHeader((string)$header);
            $colMap[$normalized] = $col;
        }

        // Résolution des colonnes selon le format détecté
        // Format ERP (export logiciel) : colonnes "code", "raison sociale", etc.
        // Format standard : colonnes "client_number", "name"
        $isErpFormat = isset($colMap['code']) && !isset($colMap['client_number']);

        if ($isErpFormat) {
            $colClientNumber = $colMap['code']           ?? null;
            $colName         = $colMap['raison sociale'] ?? $colMap['designation courante'] ?? null;
            $colPhone        = $colMap['tel']             ?? $colMap['telephone']           ?? null;
            $colEmail        = $colMap['e-mail']          ?? $colMap['email']               ?? null;
            $colActive       = $colMap['actif']           ?? null;
            // Colonnes adresse (on les concatène)
            $colsAddress     = array_filter([
                $colMap['adresse1']   ?? null,
                $colMap['adresse2']   ?? null,
                $colMap['adresse3']   ?? null,
            ]);
            $colCp           = $colMap['cp']    ?? null;
            $colVille        = $colMap['ville'] ?? null;
        } else {
            $colClientNumber = $colMap['client_number'] ?? null;
            $colName         = $colMap['name']          ?? null;
            $colPhone        = $colMap['phone']         ?? null;
            $colEmail        = $colMap['email']         ?? null;
            $colActive       = $colMap['is_active']     ?? null;
            $colsAddress     = array_filter([$colMap['address'] ?? null]);
            $colCp           = null;
            $colVille        = null;
        }

        if ($colClientNumber === null) {
            throw new \RuntimeException(
                "Colonne code client introuvable. Colonnes détectées : " . implode(', ', array_keys($colMap))
            );
        }
        if ($colName === null) {
            throw new \RuntimeException(
                "Colonne nom/raison sociale introuvable. Colonnes détectées : " . implode(', ', array_keys($colMap))
            );
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $line    = 2;

        foreach ($rows as $row) {
            $clientNumber = trim((string)($row[$colClientNumber] ?? ''));
            $name         = trim((string)($row[$colName] ?? ''));

            // Ignorer les lignes vides
            if ($clientNumber === '') {
                $line++;
                continue;
            }

            // Règle : accepter uniquement les codes 100% numériques
            if (!ctype_digit($clientNumber)) {
                $skipped++;
                $line++;
                continue;
            }

            if ($name === '') {
                $errors[] = "Ligne $line (code $clientNumber) : nom vide, ignoré.";
                $line++;
                continue;
            }

            // Téléphone
            $phone = $colPhone ? trim((string)($row[$colPhone] ?? '')) : null;

            // Email
            $email = $colEmail ? trim((string)($row[$colEmail] ?? '')) : null;

            // Statut actif : "Oui" = 1, tout le reste = 0
            $isActive = 1;
            if ($colActive !== null) {
                $activeVal = mb_strtolower(trim((string)($row[$colActive] ?? '')));
                $isActive  = ($activeVal === 'oui' || $activeVal === '1' || $activeVal === 'yes') ? 1 : 0;
            }

            // Adresse : concaténer adresse1/2/3 + CP + Ville
            $addressParts = [];
            foreach ($colsAddress as $colAddr) {
                $v = trim((string)($row[$colAddr] ?? ''));
                if ($v !== '') {
                    $addressParts[] = $v;
                }
            }
            if ($colCp !== null) {
                $cp = trim((string)($row[$colCp] ?? ''));
                if ($cp !== '') {
                    $addressParts[] = $cp;
                }
            }
            if ($colVille !== null) {
                $ville = trim((string)($row[$colVille] ?? ''));
                if ($ville !== '') {
                    $addressParts[] = $ville;
                }
            }
            $address = $addressParts ? implode(', ', $addressParts) : null;

            try {
                $affected = $this->db->execute(
                    "INSERT INTO clients (client_number, name, email, phone, address, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        name      = VALUES(name),
                        email     = VALUES(email),
                        phone     = VALUES(phone),
                        address   = VALUES(address),
                        is_active = VALUES(is_active),
                        updated_at = NOW()",
                    [
                        $clientNumber,
                        $name,
                        $email   ?: null,
                        $phone   ?: null,
                        $address ?: null,
                        $isActive,
                    ]
                )->rowCount();

                // rowCount() = 1 pour INSERT, 2 pour UPDATE (MySQL), 0 si inchangé
                if ($affected === 1) {
                    $created++;
                } elseif ($affected >= 2) {
                    $updated++;
                }
            } catch (Throwable $e) {
                $errors[] = "Ligne $line (code $clientNumber) : " . $e->getMessage();
            }

            $line++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }

    /**
     * Normalise un header Excel : minuscules + suppression des accents courants + trim.
     */
    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        // Translittération des accents courants (français)
        $map = [
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'à'=>'a','â'=>'a','ä'=>'a',
            'ù'=>'u','û'=>'u','ü'=>'u',
            'î'=>'i','ï'=>'i',
            'ô'=>'o','ö'=>'o',
            'ç'=>'c',
            'æ'=>'ae','œ'=>'oe',
        ];
        return strtr($header, $map);
    }

    private function buildWhere(string $search, int $tagId): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR c.client_number LIKE ? OR c.email LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        if ($tagId > 0) {
            $conditions[] = "ct.tag_id = ?";
            $params[]     = $tagId;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
