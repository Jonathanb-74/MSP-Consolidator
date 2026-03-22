#!/usr/bin/env php
<?php

/**
 * Script CLI — Synchronisation NinjaOne (toutes les connexions actives).
 *
 * Usage :
 *   php scripts/sync_ninjaone.php
 *
 * Cron (toutes les heures) :
 *   0 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_ninjaone.php >> /var/log/msp-sync.log 2>&1
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
use App\Modules\NinjaOne\NinjaOneApiClient;
use App\Modules\NinjaOne\NinjaOneSyncService;
use App\Modules\NinjaOne\NinjaOneTokenCache;

$startTime = microtime(true);
echo '[' . date('Y-m-d H:i:s') . "] Démarrage sync NinjaOne...\n";

try {
    $db = Database::getInstance();

    $connections = $db->fetchAll(
        "SELECT pc.id, pc.config_key
         FROM provider_connections pc
         JOIN providers p ON p.id = pc.provider_id
         WHERE p.code = 'ninjaone' AND pc.is_enabled = 1
         ORDER BY pc.id ASC"
    );

    if (empty($connections)) {
        echo "Aucune connexion NinjaOne active trouvée.\n";
        exit(0);
    }

    $globalErrors = [];

    foreach ($connections as $conn) {
        $connectionId = (int)$conn['id'];
        echo "\n  Connexion #{$connectionId} ({$conn['config_key']})...\n";

        $credentials = ProviderConfig::findConnection('ninjaone', $conn['config_key']);
        if (!$credentials) {
            echo "  WARN : credentials introuvables pour '{$conn['config_key']}' — connexion ignorée.\n";
            continue;
        }

        $tokenCache  = new NinjaOneTokenCache($credentials);
        $apiClient   = new NinjaOneApiClient($credentials, $tokenCache);
        $syncService = new NinjaOneSyncService($db, $apiClient, $connectionId);

        $summary = $syncService->syncAll('cron');

        echo sprintf(
            "  Organisations — Récupérées : %d | Créées : %d | MàJ : %d\n",
            $summary['organizations']['fetched'] ?? 0,
            $summary['organizations']['created'] ?? 0,
            $summary['organizations']['updated'] ?? 0
        );
        echo sprintf(
            "  Appareils     — Récupérés  : %d | Créés : %d | MàJ : %d\n",
            $summary['devices']['fetched'] ?? 0,
            $summary['devices']['created'] ?? 0,
            $summary['devices']['updated'] ?? 0
        );

        foreach ($summary['errors'] ?? [] as $err) {
            echo "  ERREUR : $err\n";
            $globalErrors[] = "[NinjaOne #{$connectionId}] $err";
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n[" . date('Y-m-d H:i:s') . "] Sync NinjaOne terminée en {$elapsed}s\n";
    exit(empty($globalErrors) ? 0 : 1);

} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 2);
    echo '[' . date('Y-m-d H:i:s') . "] ERREUR FATALE après {$elapsed}s : " . $e->getMessage() . "\n";
    exit(1);
}
