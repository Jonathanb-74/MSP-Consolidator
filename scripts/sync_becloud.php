#!/usr/bin/env php
<?php

/**
 * Script CLI — Synchronisation Be-Cloud (toutes les connexions actives).
 *
 * Usage :
 *   php scripts/sync_becloud.php
 *
 * Cron (toutes les heures) :
 *   0 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_becloud.php >> /var/log/msp-sync.log 2>&1
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
use App\Modules\BeCloud\BeCloudApiClient;
use App\Modules\BeCloud\BeCloudSyncService;
use App\Modules\BeCloud\BeCloudTokenCache;

$startTime = microtime(true);
echo '[' . date('Y-m-d H:i:s') . "] Démarrage sync Be-Cloud...\n";

try {
    $db = Database::getInstance();

    $connections = $db->fetchAll(
        "SELECT pc.id, pc.config_key
         FROM provider_connections pc
         JOIN providers p ON p.id = pc.provider_id
         WHERE p.code = 'becloud' AND pc.is_enabled = 1
         ORDER BY pc.id ASC"
    );

    if (empty($connections)) {
        echo "Aucune connexion Be-Cloud active trouvée.\n";
        exit(0);
    }

    $globalErrors = [];

    foreach ($connections as $conn) {
        $connectionId = (int)$conn['id'];
        echo "\n  Connexion #{$connectionId} ({$conn['config_key']})...\n";

        $credentials = ProviderConfig::findConnection('becloud', $conn['config_key']);
        if (!$credentials) {
            echo "  WARN : credentials introuvables pour '{$conn['config_key']}' — connexion ignorée.\n";
            continue;
        }

        $tokenCache  = new BeCloudTokenCache($credentials);
        $apiClient   = new BeCloudApiClient($credentials, $tokenCache);
        $syncService = new BeCloudSyncService($db, $apiClient, $connectionId);

        $summary = $syncService->syncAll('cron');

        echo sprintf(
            "  Customers     — Récupérés : %d | Créés : %d | MàJ : %d\n",
            $summary['customers']['fetched'] ?? 0,
            $summary['customers']['created'] ?? 0,
            $summary['customers']['updated'] ?? 0
        );
        echo sprintf(
            "  Subscriptions — Récupérés : %d | Créés : %d | MàJ : %d\n",
            $summary['subscriptions']['fetched'] ?? 0,
            $summary['subscriptions']['created'] ?? 0,
            $summary['subscriptions']['updated'] ?? 0
        );
        echo sprintf(
            "  Licences M365 — Récupérées : %d | Créées : %d | MàJ : %d\n",
            $summary['licenses']['fetched'] ?? 0,
            $summary['licenses']['created'] ?? 0,
            $summary['licenses']['updated'] ?? 0
        );

        foreach ($summary['errors'] ?? [] as $err) {
            echo "  ERREUR : $err\n";
            $globalErrors[] = "[BeCloud #{$connectionId}] $err";
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n[" . date('Y-m-d H:i:s') . "] Sync Be-Cloud terminée en {$elapsed}s\n";
    exit(empty($globalErrors) ? 0 : 1);

} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 2);
    echo '[' . date('Y-m-d H:i:s') . "] ERREUR FATALE après {$elapsed}s : " . $e->getMessage() . "\n";
    exit(1);
}
