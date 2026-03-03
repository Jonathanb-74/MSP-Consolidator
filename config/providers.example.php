<?php

/**
 * Exemple de configuration fournisseurs.
 * Copier ce fichier en providers.php et renseigner les valeurs.
 */
return [
    'eset' => [
        'username' => 'your_eset_email@domain.com',
        'password' => 'your_eset_password',
        'base_url' => 'https://mspapi.eset.com/api',
        'enabled'  => true,
    ],
    'ninjaone' => [
        'client_id'     => 'your_client_id',
        'client_secret' => 'your_client_secret',
        'base_url'      => 'https://app.ninjarmm.com',
        'enabled'       => false,
    ],
    'wasabi' => [
        'access_key' => 'your_access_key',
        'secret_key' => 'your_secret_key',
        'enabled'    => false,
    ],
    'veeam' => [
        'url'      => 'https://your-veeam-server.com',
        'username' => 'your_username',
        'password' => 'your_password',
        'enabled'  => false,
    ],
    'infomaniak' => [
        'api_token' => 'your_api_token',
        'enabled'   => false,
    ],
];
