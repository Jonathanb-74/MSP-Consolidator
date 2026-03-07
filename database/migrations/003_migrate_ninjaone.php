<?php
/**
 * Migration 003 — Insertion provider NinjaOne + provider_connections
 *
 * À exécuter APRÈS le SQL 003_add_ninjaone.sql
 * Usage : php database/migrations/003_migrate_ninjaone.php
 */

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';

$dbConfig = require APP_ROOT . '/config/database.php';

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4",
    $dbConfig['user'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function log_msg(string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

log_msg("=== Migration NinjaOne ===");

// 1. Insérer le provider si absent
$existing = $pdo->query("SELECT id FROM providers WHERE code = 'ninjaone' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    $providerId = (int)$existing['id'];
    log_msg("Provider 'ninjaone' déjà présent (id={$providerId}).");
} else {
    $pdo->prepare("INSERT INTO providers (code, name) VALUES ('ninjaone', 'NinjaOne')")->execute();
    $providerId = (int)$pdo->lastInsertId();
    log_msg("Provider 'ninjaone' créé (id={$providerId}).");
}

// 2. Insérer les connexions depuis config/providers.php
$providerConfig = require APP_ROOT . '/config/providers.php';
$ninjaConns     = $providerConfig['ninjaone'] ?? [];

if (empty($ninjaConns)) {
    log_msg("Aucune connexion 'ninjaone' dans config/providers.php. Rien à faire.");
} else {
    // Normaliser si format plat
    if (array_keys($ninjaConns) !== range(0, count($ninjaConns) - 1)) {
        $ninjaConns = [array_merge(['key' => 'default', 'name' => 'Console principale'], $ninjaConns)];
    }

    foreach ($ninjaConns as $conn) {
        $configKey = $conn['key']  ?? 'default';
        $name      = $conn['name'] ?? 'NinjaOne';
        $enabled   = isset($conn['enabled']) ? (int)(bool)$conn['enabled'] : 1;

        $stmt = $pdo->prepare(
            "INSERT INTO provider_connections (provider_id, config_key, name, is_enabled)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), is_enabled = VALUES(is_enabled)"
        );
        $stmt->execute([$providerId, $configKey, $name, $enabled]);

        $connId = $pdo->lastInsertId() ?: $pdo->query(
            "SELECT id FROM provider_connections WHERE provider_id = {$providerId} AND config_key = " . $pdo->quote($configKey)
        )->fetchColumn();

        log_msg("Connexion '{$name}' (config_key={$configKey}) → id={$connId}");
    }
}

log_msg("");
log_msg("=== Migration NinjaOne terminée ===");
