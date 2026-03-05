<?php
/**
 * Migration 001 — Peuplement provider_connections depuis config/providers.php
 *
 * À exécuter APRÈS le SQL 001_add_provider_connections.sql
 * Usage : php database/migrations/001_migrate_connections.php
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

$providerConfig = require APP_ROOT . '/config/providers.php';

// Helpers
function log_msg(string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function normalize_connections(array $raw, string $code): array {
    if (empty($raw)) return [];
    // Détection format plat (clés = strings, pas d'index numérique)
    if (array_keys($raw) !== range(0, count($raw) - 1)) {
        return [array_merge(['key' => 'default', 'name' => 'Connexion principale'], $raw)];
    }
    return $raw;
}

// ── Étape 1 : Créer les provider_connections depuis la config ──────────────

log_msg("=== Migration provider_connections ===");

$providers = $pdo->query("SELECT id, code, name FROM providers")->fetchAll(PDO::FETCH_ASSOC);

foreach ($providers as $provider) {
    $code = $provider['code'];
    $raw  = $providerConfig[$code] ?? [];
    $connections = normalize_connections($raw, $code);

    if (empty($connections)) {
        log_msg("  [{$code}] Aucune connexion dans la config, ignoré.");
        continue;
    }

    foreach ($connections as $conn) {
        $configKey = $conn['key'] ?? 'default';
        $name      = $conn['name'] ?? 'Connexion principale';
        $enabled   = isset($conn['enabled']) ? (int)(bool)$conn['enabled'] : 1;

        // Upsert
        $stmt = $pdo->prepare(
            "INSERT INTO provider_connections (provider_id, config_key, name, is_enabled)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), is_enabled = VALUES(is_enabled)"
        );
        $stmt->execute([(int)$provider['id'], $configKey, $name, $enabled]);

        $connectionId = $pdo->lastInsertId() ?: $pdo->query(
            "SELECT id FROM provider_connections WHERE provider_id = {$provider['id']} AND config_key = " . $pdo->quote($configKey)
        )->fetchColumn();

        log_msg("  [{$code}] Connexion '{$name}' (config_key={$configKey}) → id={$connectionId}");
    }
}

// ── Étape 2 : Mettre à jour client_provider_mappings.connection_id ─────────

log_msg("");
log_msg("Mise à jour client_provider_mappings.connection_id...");

// Pour chaque mapping sans connection_id, assigner la première connexion active du provider
$mappings = $pdo->query(
    "SELECT id, provider_id FROM client_provider_mappings WHERE connection_id IS NULL"
)->fetchAll(PDO::FETCH_ASSOC);

$connectionCache = [];

foreach ($mappings as $mapping) {
    $pid = $mapping['provider_id'];

    if (!isset($connectionCache[$pid])) {
        $row = $pdo->query(
            "SELECT id FROM provider_connections WHERE provider_id = {$pid} AND is_enabled = 1 ORDER BY id ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $connectionCache[$pid] = $row ? (int)$row['id'] : null;
    }

    if ($connectionCache[$pid]) {
        $pdo->prepare(
            "UPDATE client_provider_mappings SET connection_id = ? WHERE id = ?"
        )->execute([$connectionCache[$pid], $mapping['id']]);
    }
}

log_msg("  " . count($mappings) . " mapping(s) mis à jour.");

// ── Étape 3 : Mettre à jour sync_logs.connection_id ───────────────────────

log_msg("");
log_msg("Mise à jour sync_logs.connection_id...");

$logs = $pdo->query(
    "SELECT id, provider_id FROM sync_logs WHERE connection_id IS NULL"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as $log) {
    $pid = $log['provider_id'];
    if (!isset($connectionCache[$pid])) {
        $row = $pdo->query(
            "SELECT id FROM provider_connections WHERE provider_id = {$pid} AND is_enabled = 1 ORDER BY id ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $connectionCache[$pid] = $row ? (int)$row['id'] : null;
    }
    if ($connectionCache[$pid]) {
        $pdo->prepare("UPDATE sync_logs SET connection_id = ? WHERE id = ?")->execute([$connectionCache[$pid], $log['id']]);
    }
}

log_msg("  " . count($logs) . " log(s) mis à jour.");

// ── Étape 4 : Mettre à jour eset_companies.connection_id ─────────────────

log_msg("");
log_msg("Mise à jour eset_companies.connection_id...");

$esetProvider = $pdo->query("SELECT id FROM providers WHERE code = 'eset' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($esetProvider) {
    $pid = (int)$esetProvider['id'];
    if (isset($connectionCache[$pid]) && $connectionCache[$pid]) {
        $updated = $pdo->exec(
            "UPDATE eset_companies SET connection_id = {$connectionCache[$pid]} WHERE connection_id IS NULL"
        );
        log_msg("  {$updated} company(ies) mis à jour.");
    }
}

// ── Étape 5 : Rendre connection_id NOT NULL + contraintes ─────────────────

log_msg("");
log_msg("Finalisation contraintes FK...");

try {
    $pdo->exec("ALTER TABLE `client_provider_mappings`
        MODIFY COLUMN `connection_id` SMALLINT UNSIGNED NOT NULL,
        ADD FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)");
    log_msg("  client_provider_mappings.connection_id → NOT NULL + FK OK");
} catch (Exception $e) {
    log_msg("  WARN client_provider_mappings: " . $e->getMessage());
}

try {
    $pdo->exec("ALTER TABLE `sync_logs`
        MODIFY COLUMN `connection_id` SMALLINT UNSIGNED NOT NULL,
        ADD FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)");
    log_msg("  sync_logs.connection_id → NOT NULL + FK OK");
} catch (Exception $e) {
    log_msg("  WARN sync_logs: " . $e->getMessage());
}

try {
    $pdo->exec("ALTER TABLE `client_provider_mappings`
        DROP INDEX `uq_provider_client`,
        ADD UNIQUE KEY `uq_connection_client` (`connection_id`, `provider_client_id`)");
    log_msg("  Contrainte UNIQUE client_provider_mappings mise à jour OK");
} catch (Exception $e) {
    log_msg("  WARN contrainte UNIQUE: " . $e->getMessage());
}

log_msg("");
log_msg("=== Migration terminée ===");
