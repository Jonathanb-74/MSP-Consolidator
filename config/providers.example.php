<?php

/**
 * Configuration des connexions fournisseurs — EXEMPLE
 *
 * Copier ce fichier en providers.php et renseigner les valeurs réelles.
 *
 * Format multi-connexions : chaque fournisseur est un tableau de connexions.
 * Chaque connexion a une clé unique `key` référencée en base de données.
 * Vous pouvez définir plusieurs connexions par fournisseur (ex : plusieurs consoles ESET).
 *
 * Champs communs à toutes les connexions :
 *   key     — identifiant unique (snake_case, ex: "principale", "client_be")
 *   name    — nom affiché dans l'interface
 *   enabled — true/false (désactiver sans supprimer la config)
 */
return [

    // ── ESET MSP Administrator 2 ─────────────────────────────────────────────
    // Credentials disponibles dans la console EMA2 → API Access
    'eset' => [
        [
            'key'      => 'principale',
            'name'     => 'Console principale',
            'username' => 'api@votre-domaine.com',
            'password' => 'votre_mot_de_passe_api',
            'base_url' => 'https://mspapi.eset.com/api',
            'enabled'  => true,
        ],
        // Exemple de 2e console (multi-tenant) :
        // [
        //     'key'      => 'client_be',
        //     'name'     => 'Console Client BE',
        //     'username' => 'api-be@votre-domaine.com',
        //     'password' => 'votre_mot_de_passe_be',
        //     'base_url' => 'https://mspapi.eset.com/api',
        //     'enabled'  => true,
        // ],
    ],

    // ── NinjaOne ─────────────────────────────────────────────────────────────
    // App OAuth2 à créer dans : Administration → Apps → API → Add
    // Scopes requis : Monitoring, Management
    // base_url : https://eu.ninjarmm.com (EU) ou https://app.ninjarmm.com (US)
    'ninjaone' => [
        [
            'key'           => 'principale',
            'name'          => 'Console principale',
            'client_id'     => 'votre_client_id',
            'client_secret' => 'votre_client_secret',
            'base_url'      => 'https://eu.ninjarmm.com',
            'enabled'       => true,
        ],
    ],

    // ── Infomaniak ───────────────────────────────────────────────────────────
    // Token API à créer dans : Manager → API → Créer un token
    // Droits requis : reseller (lecture comptes et produits revendeur)
    'infomaniak' => [
        [
            'key'       => 'principale',
            'name'      => 'Compte principal',
            'api_token' => 'votre_token_api_infomaniak',
            'base_url'  => 'https://api.infomaniak.com',
            'enabled'   => true,
        ],
    ],

    // ── Be-Cloud (CloudCockpit) ───────────────────────────────────────────────
    // Authentification OAuth2 Client Credentials
    // tenant_id et scope : valeurs fixes Be-Cloud (voir doc officielle)
    // client_id / client_secret : propres à votre compte revendeur CloudCockpit
    // csp_url : domaine CSP de votre organisation (ex: csp.votre-societe.eu)
    'becloud' => [
        [
            'key'                   => 'principale',
            'name'                  => 'Be-Cloud',
            // Valeurs fixes Be-Cloud (identiques pour tous les revendeurs) :
            'tenant_id'             => '4e806121-ff28-4286-ab4e-3be0a08f9ce0',
            'scope'                 => 'api://b92a36a4-feb8-4f47-a69c-29a180aa6d0a/.default',
            // Vos credentials revendeur CloudCockpit :
            'client_id'             => 'votre_client_id_cloudcockpit',
            'client_secret'         => 'votre_client_secret_cloudcockpit',
            'base_url'              => 'https://api.cloudcockpit.com',
            // Domaine CSP de votre organisation (valeur X-Tenant sur chaque requête) :
            'csp_url'               => 'csp.votre-societe.eu',
            // Préfixe optionnel pour X-Correlation-Id (utile pour le support) :
            'correlation_id_prefix' => '',
            'enabled'               => true,
        ],
    ],

];
