#!/usr/bin/env php
<?php

/**
 * Script CLI de synchronisation ESET.
 *
 * Usage :
 *   php scripts/sync_eset.php
 *   php scripts/sync_eset.php --companies-only
 *   php scripts/sync_eset.php --licenses-only
 *
 * Cron (toutes les heures) :
 *   0 * * * * www-data php /var/www/eset-msp/scripts/sync_eset.php >> /var/www/eset-msp/storage/logs/cron.log 2>&1
 */

declare(strict_types=1);

// Éviter l'exécution via navigateur
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

$appConfig = require APP_ROOT . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

use App\Core\Database;
use App\Modules\Eset\EsetApiClient;
use App\Modules\Eset\EsetSyncService;
use App\Modules\Eset\EsetTokenCache;

$options = getopt('', ['companies-only', 'licenses-only']);

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

// Écrire le PID pour permettre l'arrêt forcé via l'UI
$pidFile = APP_ROOT . '/storage/sync_eset.pid';
file_put_contents($pidFile, (string)getmypid());

echo "[$timestamp] Démarrage sync ESET (PID: " . getmypid() . ")...\n";

try {
    $db          = Database::getInstance();
    $tokenCache  = new EsetTokenCache();
    $apiClient   = new EsetApiClient($tokenCache);
    $syncService = new EsetSyncService($db, $apiClient);

    if (isset($options['companies-only'])) {
        echo "Mode : companies uniquement\n";
        $result = $syncService->syncCompanies();
        echo sprintf(
            "Companies — Récupérées : %d | Créées : %d | Mises à jour : %d\n",
            $result['fetched'],
            $result['created'],
            $result['updated']
        );
    } elseif (isset($options['licenses-only'])) {
        echo "Mode : licences uniquement\n";
        $result = $syncService->syncLicenses();
        echo sprintf(
            "Licences — Récupérées : %d | Créées : %d | Mises à jour : %d\n",
            $result['fetched'],
            $result['created'],
            $result['updated']
        );
    } else {
        $summary = $syncService->syncAll('cron');

        echo sprintf(
            "Companies — Récupérées : %d | Créées : %d | Mises à jour : %d\n",
            $summary['companies']['fetched'],
            $summary['companies']['created'],
            $summary['companies']['updated']
        );
        echo sprintf(
            "Licences  — Récupérées : %d | Créées : %d | Mises à jour : %d\n",
            $summary['licenses']['fetched'],
            $summary['licenses']['created'],
            $summary['licenses']['updated']
        );

        if (!empty($summary['errors'])) {
            foreach ($summary['errors'] as $err) {
                echo "ERREUR : $err\n";
            }
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Sync terminée en {$elapsed}s\n";
    @unlink($pidFile);
    exit(0);
} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] ERREUR FATALE après {$elapsed}s : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";

    @unlink($pidFile);

    // Écrire dans le log d'erreurs
    $logPath = APP_ROOT . '/storage/logs/sync_errors.log';
    $logDir  = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents(
        $logPath,
        "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );

    exit(1);
}
