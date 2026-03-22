#!/usr/bin/env php
<?php

/**
 * Script CLI — Synchronisation de TOUS les fournisseurs actifs.
 *
 * Usage :
 *   php scripts/sync_all.php
 *   php scripts/sync_all.php --provider=becloud     (un seul fournisseur)
 *   php scripts/sync_all.php --provider=eset,ninjaone (liste séparée par virgules)
 *
 * Cron (toutes les heures) :
 *   0 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_all.php >> /var/log/msp-sync.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

$appConfig = require APP_ROOT . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

use App\Core\Database;
use App\Core\ProviderConfig;
use App\Modules\Eset\EsetApiClient;
use App\Modules\Eset\EsetSyncService;
use App\Modules\Eset\EsetTokenCache;
use App\Modules\BeCloud\BeCloudApiClient;
use App\Modules\BeCloud\BeCloudSyncService;
use App\Modules\BeCloud\BeCloudTokenCache;
use App\Modules\NinjaOne\NinjaOneApiClient;
use App\Modules\NinjaOne\NinjaOneSyncService;
use App\Modules\NinjaOne\NinjaOneTokenCache;
use App\Modules\Infomaniak\InformaniakApiClient;
use App\Modules\Infomaniak\InformaniakSyncService;

// ── Parsing arguments ──────────────────────────────────────────────────────
$opts = getopt('', ['provider:']);
$providerFilter = [];
if (!empty($opts['provider'])) {
    $providerFilter = array_map('trim', explode(',', $opts['provider']));
}

$allProviders = ['eset', 'becloud', 'ninjaone', 'infomaniak'];
$providers    = empty($providerFilter)
    ? $allProviders
    : array_values(array_intersect($providerFilter, $allProviders));

if (empty($providers)) {
    echo "Aucun fournisseur valide spécifié. Valeurs acceptées : " . implode(', ', $allProviders) . "\n";
    exit(1);
}

// ── Démarrage ─────────────────────────────────────────────────────────────
$globalStart  = microtime(true);
$globalErrors = [];

echo '[' . date('Y-m-d H:i:s') . "] === Démarrage sync_all (fournisseurs : " . implode(', ', $providers) . ") ===\n";

try {
    $db = Database::getInstance();

    foreach ($providers as $providerCode) {

        $connections = $db->fetchAll(
            "SELECT pc.id, pc.config_key
             FROM provider_connections pc
             JOIN providers p ON p.id = pc.provider_id
             WHERE p.code = ? AND pc.is_enabled = 1
             ORDER BY pc.id ASC",
            [$providerCode]
        );

        if (empty($connections)) {
            echo "\n[" . strtoupper($providerCode) . "] Aucune connexion active — ignoré.\n";
            continue;
        }

        echo "\n[" . strtoupper($providerCode) . "] " . count($connections) . " connexion(s) active(s)...\n";

        foreach ($connections as $conn) {
            $connectionId = (int)$conn['id'];
            echo "  #{$connectionId} ({$conn['config_key']})... ";

            $credentials = ProviderConfig::findConnection($providerCode, $conn['config_key']);
            if (!$credentials) {
                echo "WARN : credentials introuvables — ignoré.\n";
                continue;
            }

            try {
                $summary = match($providerCode) {
                    'eset' => (function() use ($db, $credentials, $connectionId): array {
                        $tc = new EsetTokenCache($credentials);
                        $ac = new EsetApiClient($credentials, $tc);
                        return (new EsetSyncService($db, $ac, $connectionId))->syncAll('cron');
                    })(),

                    'becloud' => (function() use ($db, $credentials, $connectionId): array {
                        $tc = new BeCloudTokenCache($credentials);
                        $ac = new BeCloudApiClient($credentials, $tc);
                        return (new BeCloudSyncService($db, $ac, $connectionId))->syncAll('cron');
                    })(),

                    'ninjaone' => (function() use ($db, $credentials, $connectionId): array {
                        $tc = new NinjaOneTokenCache($credentials);
                        $ac = new NinjaOneApiClient($credentials, $tc);
                        return (new NinjaOneSyncService($db, $ac, $connectionId))->syncAll('cron');
                    })(),

                    'infomaniak' => (function() use ($db, $credentials, $connectionId): array {
                        $ac = new InformaniakApiClient(
                            $credentials['api_token'],
                            $credentials['base_url'] ?? 'https://api.infomaniak.com'
                        );
                        return (new InformaniakSyncService($db, $ac, $connectionId))->syncAll('cron');
                    })(),
                };

                $errors = $summary['errors'] ?? [];
                echo (empty($errors) ? "OK" : "PARTIEL (" . count($errors) . " erreur(s))") . "\n";

                // Afficher le résumé par étape
                foreach ($summary as $step => $data) {
                    if ($step === 'errors' || !is_array($data)) continue;
                    echo sprintf(
                        "    %-16s Récupérés : %d | Créés : %d | MàJ : %d\n",
                        ucfirst($step),
                        $data['fetched'] ?? 0,
                        $data['created'] ?? 0,
                        $data['updated'] ?? 0
                    );
                }

                foreach ($errors as $err) {
                    echo "    ERREUR : $err\n";
                    $globalErrors[] = "[{$providerCode} #{$connectionId}] $err";
                }

            } catch (\Throwable $e) {
                echo "ERREUR : " . $e->getMessage() . "\n";
                $globalErrors[] = "[{$providerCode} #{$connectionId}] " . $e->getMessage();
            }
        }
    }

} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $globalStart, 2);
    echo "\n[" . date('Y-m-d H:i:s') . "] ERREUR FATALE après {$elapsed}s : " . $e->getMessage() . "\n";
    exit(1);
}

// ── Résumé final ───────────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $globalStart, 2);
echo "\n[" . date('Y-m-d H:i:s') . "] === Sync terminée en {$elapsed}s";
if (!empty($globalErrors)) {
    echo " avec " . count($globalErrors) . " erreur(s) ===\n";
    foreach ($globalErrors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}
echo " — succès ===\n";
exit(0);
