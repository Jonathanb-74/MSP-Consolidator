<?php

namespace App\Controllers;

use App\Core\AppSettings;
use App\Core\Controller;
use App\Core\Database;
use App\Core\NameNormalizer;
use App\Core\ProviderConfig;

class SettingsController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /settings/connections — Liste des connexions par fournisseur
     */
    public function connections(array $params = []): void
    {
        // Charger tous les providers connus en DB
        $providers = $this->db->fetchAll(
            "SELECT id, code, name FROM providers ORDER BY name ASC"
        );

        $data = [];

        foreach ($providers as $provider) {
            $code = $provider['code'];

            // Connexions définies dans config/providers.php
            $configConns = ProviderConfig::getConnections($code);

            // Connexions enregistrées en DB pour ce provider
            $dbConns = $this->db->fetchAll(
                "SELECT * FROM provider_connections WHERE provider_id = ? ORDER BY id ASC",
                [(int)$provider['id']]
            );

            // Indexer les connexions DB par config_key
            $dbByKey = [];
            foreach ($dbConns as $dbConn) {
                $dbByKey[$dbConn['config_key']] = $dbConn;
            }

            $rows = [];
            foreach ($configConns as $cfg) {
                $key = $cfg['key'];
                $db  = $dbByKey[$key] ?? null;
                $rows[] = [
                    'config_key'   => $key,
                    'config_name'  => $cfg['name'] ?? $key,
                    'config_enabled' => $cfg['enabled'] ?? true,
                    'db'           => $db, // null si pas encore migrée
                ];
            }

            $data[] = [
                'provider'     => $provider,
                'connections'  => $rows,
                'has_config'   => !empty($configConns),
            ];
        }

        $this->render('settings/connections', [
            'pageTitle'   => 'Paramètres — Connexions',
            'breadcrumbs' => ['Dashboard' => '/', 'Paramètres' => null, 'Connexions' => null],
            'providers'   => $data,
        ]);
    }

    /**
     * POST /settings/connections/sync-config — Synchronise la DB depuis config/providers.php
     */
    public function syncFromConfig(array $params = []): void
    {
        $allCodes = ProviderConfig::getAllCodes();
        $created  = 0;
        $updated  = 0;

        foreach ($allCodes as $code) {
            $provider = $this->db->fetchOne(
                "SELECT id FROM providers WHERE code = ?",
                [$code]
            );
            if (!$provider) {
                continue;
            }

            $providerId = (int)$provider['id'];
            $connections = ProviderConfig::getConnections($code);

            foreach ($connections as $conn) {
                $configKey = $conn['key'];
                $name      = $conn['name'] ?? $configKey;
                $enabled   = isset($conn['enabled']) ? (int)(bool)$conn['enabled'] : 1;

                $existing = $this->db->fetchOne(
                    "SELECT id FROM provider_connections WHERE provider_id = ? AND config_key = ?",
                    [$providerId, $configKey]
                );

                if ($existing) {
                    $this->db->execute(
                        "UPDATE provider_connections SET is_enabled = ?, updated_at = NOW() WHERE id = ?",
                        [$enabled, (int)$existing['id']]
                    );
                    $updated++;
                } else {
                    $this->db->execute(
                        "INSERT INTO provider_connections (provider_id, config_key, name, is_enabled)
                         VALUES (?, ?, ?, ?)",
                        [$providerId, $configKey, $name, $enabled]
                    );
                    $created++;
                }
            }
        }

        $this->json([
            'status'  => 'ok',
            'created' => $created,
            'updated' => $updated,
            'message' => "{$created} connexion(s) créée(s), {$updated} mise(s) à jour.",
        ]);
    }

    // ── Général ───────────────────────────────────────────────────────────────

    /**
     * GET /settings/general — Paramètres applicatifs généraux
     */
    public function general(array $params = []): void
    {
        $this->render('settings/general', [
            'pageTitle'   => 'Paramètres — Général',
            'breadcrumbs' => ['Dashboard' => '/', 'Paramètres' => null, 'Général' => null],
            'settings'    => AppSettings::all(),
        ]);
    }

    /**
     * POST /settings/general/update — Mettre à jour un paramètre
     */
    public function generalUpdate(array $params = []): void
    {
        $key   = trim($_POST['key'] ?? '');
        $value = trim($_POST['value'] ?? '');

        if ($key === '') {
            $this->json(['status' => 'error', 'message' => 'Clé manquante.'], 400);
            return;
        }

        // Vérifier que la clé existe (on ne crée pas de nouvelles clés via l'UI)
        $all = AppSettings::all();
        if (!isset($all[$key])) {
            $this->json(['status' => 'error', 'message' => 'Paramètre inconnu.'], 404);
            return;
        }

        // Validation selon le type
        $type = $all[$key]['type'];
        if ($type === 'integer' && (!is_numeric($value) || (int)$value < 0)) {
            $this->json(['status' => 'error', 'message' => 'Valeur entière positive attendue.'], 400);
            return;
        }
        if ($type === 'boolean' && !in_array($value, ['0', '1', 'true', 'false'], true)) {
            $this->json(['status' => 'error', 'message' => 'Valeur booléenne attendue (0/1).'], 400);
            return;
        }

        AppSettings::set($key, $value);

        $this->json(['status' => 'ok', 'key' => $key, 'value' => $value]);
    }

    // ── Normalisation ─────────────────────────────────────────────────────────

    /**
     * GET /settings/normalisation — Règles de normalisation des noms
     */
    public function normalisation(array $params = []): void
    {
        $rules = $this->db->fetchAll(
            "SELECT id, value, type, active, created_at FROM normalization_rules ORDER BY type, CHAR_LENGTH(value) DESC, value"
        );

        $this->render('settings/normalisation', [
            'pageTitle'   => 'Paramètres — Normalisation',
            'breadcrumbs' => ['Dashboard' => '/', 'Paramètres' => null, 'Normalisation' => null],
            'rules'       => $rules,
        ]);
    }

    /**
     * POST /settings/normalisation/store — Ajouter une règle
     */
    public function normalisationStore(array $params = []): void
    {
        $value = trim($_POST['value'] ?? '');
        $type  = $_POST['type'] ?? 'custom';

        if ($value === '') {
            $this->json(['status' => 'error', 'message' => 'La valeur ne peut pas être vide.'], 400);
            return;
        }

        if (!in_array($type, ['legal_form', 'custom'], true)) {
            $this->json(['status' => 'error', 'message' => 'Type invalide.'], 400);
            return;
        }

        try {
            $this->db->execute(
                "INSERT INTO normalization_rules (value, type) VALUES (?, ?)",
                [$value, $type]
            );
            $id = (int)$this->db->lastInsertId();
            NameNormalizer::clearCache();

            $this->json(['status' => 'ok', 'id' => $id, 'value' => $value, 'type' => $type]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'uq_norm')) {
                $this->json(['status' => 'error', 'message' => 'Cette valeur existe déjà.'], 409);
            } else {
                $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * POST /settings/normalisation/toggle — Activer / désactiver une règle
     */
    public function normalisationToggle(array $params = []): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['status' => 'error', 'message' => 'ID invalide.'], 400);
            return;
        }

        $row = $this->db->fetchOne("SELECT active FROM normalization_rules WHERE id = ?", [$id]);
        if (!is_array($row)) {
            $this->json(['status' => 'error', 'message' => 'Règle introuvable.'], 404);
            return;
        }

        $newActive = $row['active'] ? 0 : 1;
        $this->db->execute("UPDATE normalization_rules SET active = ? WHERE id = ?", [$newActive, $id]);
        NameNormalizer::clearCache();

        $this->json(['status' => 'ok', 'active' => $newActive]);
    }

    /**
     * POST /settings/normalisation/delete — Supprimer une règle
     */
    public function normalisationDelete(array $params = []): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['status' => 'error', 'message' => 'ID invalide.'], 400);
            return;
        }

        $this->db->execute("DELETE FROM normalization_rules WHERE id = ?", [$id]);
        NameNormalizer::clearCache();

        $this->json(['status' => 'ok']);
    }

    // ── Connexions ────────────────────────────────────────────────────────────

    /**
     * POST /settings/connections/rename — Renommer une connexion
     */
    public function renameConnection(array $params = []): void
    {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 || $name === '') {
            $this->json(['status' => 'error', 'message' => 'Paramètres invalides.'], 400);
            return;
        }

        $this->db->execute(
            "UPDATE provider_connections SET name = ?, updated_at = NOW() WHERE id = ?",
            [$name, $id]
        );

        $this->json(['status' => 'ok', 'name' => $name]);
    }
}
