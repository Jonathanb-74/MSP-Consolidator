#!/usr/bin/env php
<?php

/**
 * Script CLI — Synchronisation Infomaniak (toutes les connexions actives).
 *
 * Usage :
 *   php scripts/sync_infomaniak.php
 *
 * Cron (toutes les heures) :
 *   0 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_infomaniak.php >> /var/log/msp-sync.log 2>&1
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
use App\Modules\Infomaniak\InformaniakApiClient;
use App\Modules\Infomaniak\InformaniakSyncService;

$startTime = microtime(true);
echo '[' . date('Y-m-d H:i:s') . "] Démarrage sync Infomaniak...\n";

try {
    $db = Database::getInstance();

    $connections = $db->fetchAll(
        "SELECT pc.id, pc.config_key
         FROM provider_connections pc
         JOIN providers p ON p.id = pc.provider_id
         WHERE p.code = 'infomaniak' AND pc.is_enabled = 1
         ORDER BY pc.id ASC"
    );

    if (empty($connections)) {
        echo "Aucune connexion Infomaniak active trouvée.\n";
        exit(0);
    }

    $globalErrors = [];

    foreach ($connections as $conn) {
        $connectionId = (int)$conn['id'];
        echo "\n  Connexion #{$connectionId} ({$conn['config_key']})...\n";

        $credentials = ProviderConfig::findConnection('infomaniak', $conn['config_key']);
        if (!$credentials) {
            echo "  WARN : credentials introuvables pour '{$conn['config_key']}' — connexion ignorée.\n";
            continue;
        }

        $apiClient   = new InformaniakApiClient(
            $credentials['api_token'],
            $credentials['base_url'] ?? 'https://api.infomaniak.com'
        );
        $syncService = new InformaniakSyncService($db, $apiClient, $connectionId);

        $summary = $syncService->syncAll('cron');

        echo sprintf(
            "  Comptes  — Traités : %d | Créés : %d | MàJ : %d\n",
            $summary['accounts']['fetched'] ?? 0,
            $summary['accounts']['created'] ?? 0,
            $summary['accounts']['updated'] ?? 0
        );
        echo sprintf(
            "  Produits — Traités : %d | Créés : %d | MàJ : %d\n",
            $summary['products']['fetched'] ?? 0,
            $summary['products']['created'] ?? 0,
            $summary['products']['updated'] ?? 0
        );

        foreach ($summary['errors'] ?? [] as $err) {
            echo "  ERREUR : $err\n";
            $globalErrors[] = "[Infomaniak #{$connectionId}] $err";
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n[" . date('Y-m-d H:i:s') . "] Sync Infomaniak terminée en {$elapsed}s\n";
    exit(empty($globalErrors) ? 0 : 1);

} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 2);
    echo '[' . date('Y-m-d H:i:s') . "] ERREUR FATALE après {$elapsed}s : " . $e->getMessage() . "\n";
    exit(1);
}
