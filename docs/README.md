# MSP Consolidator — Documentation

**MSP Consolidator** est une application web PHP permettant aux prestataires informatiques (MSP) de centraliser et visualiser l'ensemble de leurs licences, abonnements et équipements gérés chez différents fournisseurs depuis une interface unique.

---

## Table des matières

1. [Objectif de l'application](#objectif)
2. [Installation](installation.md)
   - [Synchronisation automatique (cron)](installation.md#7-automatiser-la-synchronisation-cron)
3. [⚠️ Sécurité — à lire impérativement](securite.md)
4. [Fonctionnement des fournisseurs (providers)](providers.md)
   - [Scripts CLI de synchronisation](providers.md#scripts-cli-de-synchronisation)
5. [Système de normalisation](normalisation.md)
6. Configuration des fournisseurs
   - [ESET](providers/eset.md)
   - [Be-Cloud / Microsoft 365](providers/becloud.md)
   - [NinjaOne](providers/ninjaone.md)
   - [Infomaniak](providers/infomaniak.md)

---

## Objectif

Les MSP gèrent des dizaines de clients répartis sur plusieurs plateformes fournisseurs. Sans outil centralisé, suivre les consommations de licences, les renouvellements et les sur/sous-utilisations implique de jongler entre plusieurs consoles d'administration.

**MSP Consolidator** résout ce problème en :

- **Synchronisant** automatiquement les données de chaque fournisseur (ESET, Be-Cloud, NinjaOne, Infomaniak) dans une base de données locale
- **Mappant** les clients fournisseurs aux clients internes via un système de correspondance par nom ou identifiant
- **Affichant** un récapitulatif unifié (page *Récap Licences*) : toutes les licences de tous les clients sur une seule page
- **Alertant** visuellement sur les sur-utilisations, expirations imminentes et licences non assignées
- **Générant** des rapports PDF par client

### Ce que l'application N'est PAS

- Un outil de facturation
- Un portail client
- Un outil de provisioning (elle ne crée pas de licences chez les fournisseurs)

---

## Architecture technique

| Composant | Technologie |
|---|---|
| Backend | PHP 8.1+ (MVC maison) |
| Base de données | MySQL / MariaDB |
| Frontend | Bootstrap 5.3 + Bootstrap Icons |
| Génération PDF | Dompdf |
| Dépendances | Composer |

L'application fonctionne sans framework lourd. Le routeur, le conteneur de base de données et le système de vues sont des composants internes légers situés dans `app/Core/`.
