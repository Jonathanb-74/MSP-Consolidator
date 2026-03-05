<?php
/**
 * Migration 002 — Insertion provider Be-Cloud + provider_connections
 *
 * À exécuter APRÈS le SQL 002_add_becloud.sql
 * Usage : php database/migrations/002_migrate_becloud.php
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

log_msg("=== Migration Be-Cloud ===");

// 1. Insérer le provider si absent
$existing = $pdo->query("SELECT id FROM providers WHERE code = 'becloud' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    $providerId = (int)$existing['id'];
    log_msg("Provider 'becloud' déjà présent (id={$providerId}).");
} else {
    $pdo->prepare("INSERT INTO providers (code, name) VALUES ('becloud', 'Be-Cloud')")->execute();
    $providerId = (int)$pdo->lastInsertId();
    log_msg("Provider 'becloud' créé (id={$providerId}).");
}

// 2. Insérer les connexions depuis config/providers.php
$providerConfig = require APP_ROOT . '/config/providers.php';
$beCloudConns   = $providerConfig['becloud'] ?? [];

if (empty($beCloudConns)) {
    log_msg("Aucune connexion 'becloud' dans config/providers.php. Rien à faire.");
} else {
    // Normaliser si format plat
    if (array_keys($beCloudConns) !== range(0, count($beCloudConns) - 1)) {
        $beCloudConns = [array_merge(['key' => 'default', 'name' => 'Connexion principale'], $beCloudConns)];
    }

    foreach ($beCloudConns as $conn) {
        $configKey = $conn['key']  ?? 'default';
        $name      = $conn['name'] ?? 'Be-Cloud';
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
log_msg("=== Migration Be-Cloud terminée ===");
