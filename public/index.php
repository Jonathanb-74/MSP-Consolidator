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

// Routeur
use App\Core\Router;
use App\Controllers\DashboardController;
use App\Controllers\ClientController;
use App\Controllers\MappingController;
use App\Modules\Eset\EsetController;

$router = new Router();

// Dashboard
$router->get('/',                [DashboardController::class, 'index']);
$router->get('/dashboard',       [DashboardController::class, 'index']);

// Clients
$router->get('/clients',         [ClientController::class, 'index']);
$router->get('/clients/import',  [ClientController::class, 'importForm']);
$router->post('/clients/import', [ClientController::class, 'importProcess']);

// Mapping fournisseur ↔ client
$router->get('/mapping',                [MappingController::class, 'index']);
$router->post('/mapping/link',          [MappingController::class, 'link']);
$router->post('/mapping/unlink',        [MappingController::class, 'unlink']);
$router->post('/mapping/confirm-bulk',  [MappingController::class, 'confirmBulk']);
$router->post('/mapping/auto-confirm',  [MappingController::class, 'autoConfirm']);

// ESET
$router->get('/eset/licenses',    [EsetController::class, 'licenses']);
$router->get('/eset/sync-logs',   [EsetController::class, 'syncLogs']);
$router->post('/eset/sync',        [EsetController::class, 'sync']);
$router->post('/eset/sync-cancel', [EsetController::class, 'syncCancel']);
$router->get('/eset/sync-status',  [EsetController::class, 'syncStatus']);

$router->dispatch();
