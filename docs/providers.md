# Fonctionnement des fournisseurs (Providers)

## Vue d'ensemble

Chaque fournisseur intégré dans MSP Consolidator suit la même architecture modulaire :

```
app/Modules/{NomProvider}/
├── {Provider}ApiClient.php     ← Communication avec l'API REST du fournisseur
├── {Provider}SyncService.php   ← Logique de synchronisation vers la base locale
├── {Provider}Controller.php    ← Pages web (liste, détail, sync, logs)
└── {Provider}TokenCache.php    ← Gestion du token OAuth2 (si applicable)
```

La configuration des credentials se fait dans `config/providers.php`. Les données synchronisées sont stockées dans des tables dédiées (`eset_licenses`, `be_cloud_subscriptions`, `ninjaone_devices`, etc.).

---

## Connexions multi-comptes

Chaque fournisseur supporte **plusieurs connexions** simultanées (plusieurs comptes/consoles). Par exemple, si vous revendez ESET pour deux entités juridiques distinctes, vous pouvez configurer deux connexions ESET.

### Dans `config/providers.php`

```php
'eset' => [
    [
        'key'      => 'principale',    // Identifiant unique (référencé en DB)
        'name'     => 'Console FCI',   // Nom affiché dans l'interface
        'username' => 'api@exemple.com',
        'password' => 'secret',
        'base_url' => 'https://mspapi.eset.com/api',
        'enabled'  => true,
    ],
    [
        'key'      => 'client_be',
        'name'     => 'Console Client BE',
        'username' => 'api-be@exemple.com',
        'password' => 'secret-be',
        'base_url' => 'https://mspapi.eset.com/api',
        'enabled'  => true,
    ],
],
```

### En base de données

Chaque connexion active est enregistrée dans la table `provider_connections` avec son `connection_key` (valeur du champ `key`). Toutes les données synchronisées sont liées à leur connexion d'origine via `connection_id`.

---

## Cycle de synchronisation

1. **Déclenchement** : manuel via le bouton *Sync maintenant* dans l'interface, ou automatique via les scripts CLI
2. **Appel API** : le `ApiClient` récupère les données depuis l'API du fournisseur (pagination gérée automatiquement)
3. **Upsert DB** : le `SyncService` insère ou met à jour les enregistrements en base (clé unique = identifiant fournisseur + connection_id)
4. **Logs** : chaque synchronisation génère un enregistrement dans `sync_logs` (statut, durée, nombre d'éléments, messages d'erreur éventuels)

Les logs sont consultables depuis la page de chaque fournisseur via le bouton **Logs**.

## Scripts CLI de synchronisation

Des scripts PHP en ligne de commande sont disponibles dans `scripts/` pour automatiser la synchronisation (cron, planificateur de tâches).

```bash
# Tous les fournisseurs d'un coup
php scripts/sync_all.php

# Un fournisseur spécifique
php scripts/sync_all.php --provider=becloud

# Scripts individuels
php scripts/sync_eset.php
php scripts/sync_becloud.php
php scripts/sync_ninjaone.php
php scripts/sync_infomaniak.php
```

Chaque script :
- Boucle sur **toutes les connexions actives** du fournisseur (multi-connexions supportées)
- Retourne le code de sortie `0` (succès) ou `1` (erreurs partielles)
- Refuse l'exécution via navigateur (sécurité)

Voir [Installation — section 7](installation.md#7-automatiser-la-synchronisation-cron) pour la configuration cron complète.

---

## Mapping fournisseur ↔ client interne

La table `client_provider_mappings` fait le lien entre :
- Un **client interne** (table `clients`) — saisi manuellement dans MSP Consolidator
- Un **client fournisseur** (ex : une société dans la console ESET, un customer Be-Cloud)

### Auto-mapping

Lors d'une synchronisation, l'application tente de faire correspondre automatiquement les clients fournisseurs aux clients internes par **similarité de nom** (algorithme de distance de Levenshtein normalisée). Les règles de [normalisation](normalisation.md) sont appliquées avant la comparaison pour améliorer la précision.

Un mapping auto-détecté est marqué `is_confirmed = 0` (non confirmé). Il doit être **validé manuellement** depuis la page **Mapping** avant d'être pris en compte dans le récap licences.

### Mapping manuel

Depuis la page **Mapping** (accessible depuis chaque vue fournisseur), vous pouvez :
- Confirmer un mapping suggéré automatiquement
- Créer un mapping manuellement (recherche du client interne)
- Dissocier un mapping existant

### Identifiant fournisseur

Certains fournisseurs permettent de stocker un `internal_identifier` (numéro client interne) directement dans leur console. MSP Consolidator peut utiliser cet identifiant comme critère de correspondance prioritaire — plus fiable que la comparaison par nom.

---

## Fournisseurs disponibles

| Fournisseur | Module | Données synchronisées |
|---|---|---|
| [ESET](providers/eset.md) | `app/Modules/Eset/` | Licences, sociétés, usage par poste |
| [Be-Cloud / M365](providers/becloud.md) | `app/Modules/BeCloud/` | Clients, abonnements, licences M365 |
| [NinjaOne](providers/ninjaone.md) | `app/Modules/NinjaOne/` | Organisations, appareils, compteurs RMM/NMS/MDM |
| [Infomaniak](providers/infomaniak.md) | `app/Modules/Infomaniak/` | Comptes, produits, dates d'expiration |

---

## Activation / Désactivation

Un fournisseur n'apparaît dans le menu et les pages que s'il possède au moins une connexion avec `enabled = true` dans `config/providers.php` ET une entrée correspondante dans la table `providers` de la base.

Pour désactiver temporairement un fournisseur sans supprimer ses données, passez `'enabled' => false` dans sa configuration.
