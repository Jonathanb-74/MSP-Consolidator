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
     * GET /clients — Liste des clients avec filtres
     */
    public function index(array $params = []): void
    {
        $search    = trim($_GET['search'] ?? '');
        $structure = $_GET['structure'] ?? '';
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 50;
        $offset    = ($page - 1) * $perPage;

        [$whereSql, $whereParams] = $this->buildWhere($search, $structure);

        $total = $this->db->count(
            "SELECT COUNT(*) FROM clients c
             JOIN structures s ON s.id = c.structure_id $whereSql",
            $whereParams
        );

        $clients = $this->db->fetchAll(
            "SELECT c.*, s.code AS structure_code,
                (SELECT COUNT(*) FROM client_provider_mappings
                 WHERE client_id = c.id) AS provider_count
             FROM clients c
             JOIN structures s ON s.id = c.structure_id
             $whereSql
             ORDER BY s.code, c.name
             LIMIT $perPage OFFSET $offset",
            $whereParams
        );

        $structures = $this->db->fetchAll("SELECT code FROM structures ORDER BY code");

        $this->render('clients/index', [
            'pageTitle'  => 'Clients',
            'breadcrumbs'=> ['Dashboard' => '/', 'Clients' => null],
            'clients'    => $clients,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'search'     => $search,
            'structure'  => $structure,
            'structures' => array_column($structures, 'code'),
        ]);
    }

    /**
     * GET /clients/import — Formulaire d'import Excel
     */
    public function importForm(array $params = []): void
    {
        $structures = $this->db->fetchAll("SELECT code, name FROM structures ORDER BY code");

        $this->render('clients/import', [
            'pageTitle'  => 'Importer des clients',
            'breadcrumbs'=> ['Dashboard' => '/', 'Clients' => '/clients', 'Import Excel' => null],
            'structures' => $structures,
        ]);
    }

    /**
     * POST /clients/import — Traitement de l'import Excel
     */
    public function importProcess(array $params = []): void
    {
        $structureCode = trim($_POST['structure'] ?? '');

        // Validation structure
        $structure = $this->db->fetchOne(
            "SELECT id, code FROM structures WHERE code = ? LIMIT 1",
            [$structureCode]
        );

        if (!$structure) {
            $this->flash('danger', 'Structure invalide.');
            $this->redirect('/clients/import');
            return;
        }

        // Validation fichier
        if (empty($_FILES['excel_file']['tmp_name']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Aucun fichier reçu ou erreur upload.');
            $this->redirect('/clients/import');
            return;
        }

        $file     = $_FILES['excel_file'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['xlsx', 'xls', 'csv'];

        if (!in_array($ext, $allowed, true)) {
            $this->flash('danger', 'Format non supporté. Utilisez .xlsx, .xls ou .csv.');
            $this->redirect('/clients/import');
            return;
        }

        // Déplacer vers storage/uploads
        $appConfig  = require dirname(__DIR__, 2) . '/config/app.php';
        $uploadDir  = $appConfig['uploads_path'];
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }
        $tmpPath = $uploadDir . '/' . uniqid('import_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $tmpPath);

        try {
            $result = $this->processExcelFile($tmpPath, (int)$structure['id']);
            @unlink($tmpPath);

            $this->flash(
                'success',
                sprintf(
                    'Import %s terminé : %d créé(s), %d mis à jour, %d ignoré(s) (code non numérique), %d erreur(s).',
                    $structureCode,
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

    // ── Helpers ────────────────────────────────────────────────────

    private function processExcelFile(string $filePath, int $structureId): array
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
                    "INSERT INTO clients
                        (client_number, structure_id, name, email, phone, address, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        name       = VALUES(name),
                        email      = VALUES(email),
                        phone      = VALUES(phone),
                        address    = VALUES(address),
                        is_active  = VALUES(is_active),
                        updated_at = NOW()",
                    [
                        $clientNumber,
                        $structureId,
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

    private function buildWhere(string $search, string $structure): array
    {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(c.name LIKE ? OR c.client_number LIKE ? OR c.email LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        if ($structure !== '') {
            $conditions[] = "s.code = ?";
            $params[]     = $structure;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
