# ⚠️ Sécurité — Points critiques

> **Cette section doit être lue avant tout déploiement.**

---

## Absence totale d'authentification

**MSP Consolidator ne dispose d'aucun système de connexion, d'authentification ou de contrôle d'accès.**

Toute personne pouvant accéder à l'URL de l'application a un accès complet et immédiat à :

- L'ensemble des données clients (noms, numéros, tags)
- Tous les identifiants API des fournisseurs (ESET, Be-Cloud, NinjaOne, Infomaniak)
- Les capacités d'écriture : ajout/suppression de clients, modification du mapping, lancement de synchronisations

Ce comportement est **volontaire** : l'application est conçue pour fonctionner sur un réseau de confiance (interne ou VPN), sans friction pour les techniciens.

---

## Règle absolue : ne jamais exposer l'application sur Internet

L'application **ne doit jamais** être accessible depuis une adresse IP publique sans protection réseau préalable.

### Déploiements acceptables ✅

| Scénario | Explication |
|---|---|
| **Réseau local (LAN)** | Accessible uniquement depuis les postes du bureau |
| **VPN d'entreprise** | Accès réservé aux techniciens connectés au VPN |
| **Serveur interne protégé** | Derrière un firewall qui bloque les accès extérieurs |
| **Tunnel SSH** | Accès à distance via `ssh -L 8080:localhost:80 user@serveur` |

### Déploiements interdits ❌

| Scénario | Risque |
|---|---|
| Serveur public sans authentification | Accès libre à toutes les données et credentials |
| URL partagée par e-mail | Accessible par quiconque possède le lien |
| Hébergement mutualisé public | Hors de contrôle, risque d'exposition |

---

## Données sensibles stockées

### Dans `config/providers.php`

Ce fichier contient en clair les identifiants API de tous les fournisseurs :

- Mots de passe et clés API ESET
- `client_id` et `client_secret` OAuth2 Be-Cloud / Microsoft Entra
- `client_id` et `client_secret` OAuth2 NinjaOne
- Token API Infomaniak

**Recommandations :**
- Ne jamais committer ce fichier dans un dépôt Git public
- Ajouter `config/providers.php` au `.gitignore` si le dépôt est public
- Restreindre les permissions du fichier : `chmod 640 config/providers.php`

### Dans la base de données

La base de données contient :
- Les données clients synchronisées depuis les fournisseurs
- Les connexions fournisseurs (table `provider_connections`) — les secrets y sont également stockés en clair

Sécurisez l'accès MySQL : n'accordez pas de connexions distantes inutiles, utilisez un mot de passe fort.

---

## Recommandations supplémentaires

### Protection HTTP basique (solution minimale)

Si vous devez exposer l'application sur un réseau semi-ouvert, ajoutez a minima une authentification HTTP Basic via `.htaccess` :

```apache
AuthType Basic
AuthName "MSP Consolidator — Accès restreint"
AuthUserFile /chemin/absolu/.htpasswd
Require valid-user
```

Générez le fichier de mots de passe :

```bash
htpasswd -c /chemin/absolu/.htpasswd technicien1
```

> **Important :** l'authentification HTTP Basic envoie les credentials en clair si HTTPS n'est pas activé. Utilisez HTTPS (Let's Encrypt ou certificat interne) si vous choisissez cette option.

### Audit régulier

- Vérifiez périodiquement les logs d'accès du serveur web
- Renouvelez les clés API fournisseurs si vous suspectez une compromission
- Supprimez les connexions fournisseurs inutilisées depuis **Paramètres → Connexions**

---

## Résumé

```
┌─────────────────────────────────────────────────────────┐
│  L'application N'a PAS de login.                        │
│  Hébergez-la UNIQUEMENT sur réseau interne ou VPN.      │
│  Ne commitez PAS config/providers.php sur Git public.   │
└─────────────────────────────────────────────────────────┘
```
