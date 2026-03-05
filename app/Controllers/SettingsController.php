<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
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
