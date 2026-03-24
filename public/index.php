<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// Autoloader Composer
require APP_ROOT . '/vendor/autoload.php';

// Initialisation : timezone, erreurs
$appConfig = require APP_ROOT . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Démarrage session
session_start();

// ── Vérification de la configuration (setup guard) ────────────────────────────
use App\Core\SetupPage;

// 1. Fichier config/database.php présent ?
if (!file_exists(APP_ROOT . '/config/database.php')) {
    SetupPage::show('config');
}

// 2. Connexion à la base de données possible ?
try {
    \App\Core\Database::getInstance();
} catch (\Throwable $e) {
    SetupPage::show('connection', $e->getMessage());
}

// 3. Tables core présentes (schema.sql importé) ?
try {
    \App\Core\Database::getInstance()->fetchOne("SELECT id FROM `clients` LIMIT 1");
} catch (\Throwable $e) {
    SetupPage::show('schema', $e->getMessage());
}
// ─────────────────────────────────────────────────────────────────────────────

// Routeur
use App\Core\Router;
use App\Controllers\DashboardController;
use App\Controllers\ClientController;
use App\Controllers\TagController;
use App\Controllers\LicenseController;
use App\Controllers\MappingController;
use App\Controllers\SettingsController;
use App\Controllers\DocsController;
use App\Controllers\CalendarController;
use App\Modules\Eset\EsetController;
use App\Modules\BeCloud\BeCloudController;
use App\Modules\NinjaOne\NinjaOneController;
use App\Modules\Infomaniak\InformaniakController;

$router = new Router();

// Dashboard
$router->get('/',                [DashboardController::class, 'index']);
$router->get('/dashboard',       [DashboardController::class, 'index']);

// Clients
$router->get('/clients',         [ClientController::class, 'index']);
$router->get('/clients/import',  [ClientController::class, 'importForm']);
$router->post('/clients/import', [ClientController::class, 'importProcess']);
$router->post('/clients/tag',    [ClientController::class, 'toggleTag']);

// Tags
$router->get('/tags',           [TagController::class, 'index']);
$router->post('/tags/create',   [TagController::class, 'create']);
$router->post('/tags/update',   [TagController::class, 'update']);
$router->post('/tags/delete',   [TagController::class, 'delete']);
$router->post('/tags/reorder',  [TagController::class, 'reorder']);

// Récap licences
$router->get('/licenses',             [LicenseController::class, 'index']);
$router->get('/licenses/{id}/report', [LicenseController::class, 'report']);

// Mapping fournisseur ↔ client
$router->get('/mapping',                [MappingController::class, 'index']);
$router->post('/mapping/link',          [MappingController::class, 'link']);
$router->post('/mapping/link-bulk',     [MappingController::class, 'linkBulk']);
$router->post('/mapping/unlink',        [MappingController::class, 'unlink']);
$router->post('/mapping/confirm-bulk',  [MappingController::class, 'confirmBulk']);
$router->post('/mapping/auto-confirm',  [MappingController::class, 'autoConfirm']);

// ESET
$router->get('/eset/licenses',      [EsetController::class, 'licenses']);
$router->get('/eset/sync-logs',     [EsetController::class, 'syncLogs']);
$router->post('/eset/sync',         [EsetController::class, 'sync']);
$router->post('/eset/sync-cancel',  [EsetController::class, 'syncCancel']);
$router->get('/eset/sync-status',   [EsetController::class, 'syncStatus']);
$router->get('/eset/debug-license', [EsetController::class, 'debugLicense']);
$router->get('/eset/debug-devices', [EsetController::class, 'debugDevices']);
$router->get('/eset/debug-history', [EsetController::class, 'debugHistory']);

// Be-Cloud
$router->get('/becloud/customers',       [BeCloudController::class, 'customers']);
$router->get('/becloud/customer-detail', [BeCloudController::class, 'customerDetail']);
$router->get('/becloud/client/{id}',     [BeCloudController::class, 'clientDetail']);
$router->get('/becloud/licenses',        [BeCloudController::class, 'licenses']);
$router->get('/becloud/sync-logs',       [BeCloudController::class, 'syncLogs']);
$router->post('/becloud/sync',           [BeCloudController::class, 'sync']);
$router->post('/becloud/sync-cancel',    [BeCloudController::class, 'syncCancel']);
$router->get('/becloud/sync-status',     [BeCloudController::class, 'syncStatus']);

// NinjaOne
$router->get('/ninjaone/licenses',     [NinjaOneController::class, 'licenses']);
$router->get('/ninjaone/devices',      [NinjaOneController::class, 'devices']);
$router->get('/ninjaone/sync-logs',    [NinjaOneController::class, 'syncLogs']);
$router->post('/ninjaone/sync',        [NinjaOneController::class, 'sync']);
$router->post('/ninjaone/sync-cancel', [NinjaOneController::class, 'syncCancel']);
$router->get('/ninjaone/sync-status',  [NinjaOneController::class, 'syncStatus']);

// Infomaniak
$router->get('/infomaniak/licenses',     [InformaniakController::class, 'licenses']);
$router->get('/infomaniak/products',     [InformaniakController::class, 'products']);
$router->get('/infomaniak/sync-logs',    [InformaniakController::class, 'syncLogs']);
$router->post('/infomaniak/sync',        [InformaniakController::class, 'sync']);
$router->post('/infomaniak/sync-cancel', [InformaniakController::class, 'syncCancel']);
$router->get('/infomaniak/sync-status',  [InformaniakController::class, 'syncStatus']);
$router->get('/infomaniak/client/{id}',  [InformaniakController::class, 'clientProducts']);

// Paramètres
$router->get('/settings/connections',                  [SettingsController::class, 'connections']);
$router->post('/settings/connections/sync-config',     [SettingsController::class, 'syncFromConfig']);
$router->post('/settings/connections/rename',          [SettingsController::class, 'renameConnection']);

$router->get('/settings/normalisation',                [SettingsController::class, 'normalisation']);
$router->post('/settings/normalisation/store',         [SettingsController::class, 'normalisationStore']);
$router->post('/settings/normalisation/toggle',        [SettingsController::class, 'normalisationToggle']);
$router->post('/settings/normalisation/delete',        [SettingsController::class, 'normalisationDelete']);

$router->get('/settings/general',                      [SettingsController::class, 'general']);
$router->post('/settings/general/update',              [SettingsController::class, 'generalUpdate']);

// Calendrier des expirations
$router->get('/calendar', [CalendarController::class, 'index']);

// Documentation
$router->get('/docs', [DocsController::class, 'index']);

$router->dispatch();
