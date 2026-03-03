<?php

return [
    'name'     => 'MSP Consolidator',
    'version'  => '1.0.0',
    'timezone' => 'Europe/Paris',
    'locale'   => 'fr_FR',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost',
    'debug'    => (bool)(getenv('APP_DEBUG') ?: true),

    // En dev local (sans certificat SSL valide), passer à true.
    // DOIT être false en production.
    'ssl_verify' => (bool)(getenv('APP_SSL_VERIFY') ?: false),

    // Chemins internes (relatifs à la racine du projet)
    'root_path'    => dirname(__DIR__),
    'storage_path' => dirname(__DIR__) . '/storage',
    'views_path'   => dirname(__DIR__) . '/resources/views',
    'cache_path'   => dirname(__DIR__) . '/storage/cache',
    'logs_path'    => dirname(__DIR__) . '/storage/logs',
    'uploads_path' => dirname(__DIR__) . '/storage/uploads',
];
