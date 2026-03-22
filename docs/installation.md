# Installation

## Prérequis

| Logiciel | Version minimale | Notes |
|---|---|---|
| PHP | 8.1 | Extensions requises : `pdo_mysql`, `json`, `curl`, `mbstring` |
| MySQL / MariaDB | 8.0 / 10.6 | Supporte `JSON_EXTRACT`, `JSON_UNQUOTE` |
| Composer | 2.x | Gestionnaire de dépendances PHP |
| Serveur web | Apache 2.4 / Nginx | Avec support URL rewriting (mod_rewrite) |

> **Environnement local recommandé :** WAMP, XAMPP ou Laragon sous Windows. L'application est prévue pour fonctionner derrière un réseau interne ou un VPN — voir [Sécurité](securite.md).

---

## 1. Récupérer le code source

```bash
git clone https://github.com/votre-organisation/MSP-Consolidator.git
cd MSP-Consolidator
```

---

## 2. Installer les dépendances PHP

```bash
composer install --no-dev
```

---

## 3. Créer la base de données

Créez une base de données MySQL vide :

```sql
CREATE DATABASE msp_consolidator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'msp_user'@'localhost' IDENTIFIED BY 'votre_mot_de_passe';
GRANT ALL PRIVILEGES ON msp_consolidator.* TO 'msp_user'@'localhost';
FLUSH PRIVILEGES;
```

Importez le schéma initial :

```bash
mysql -u msp_user -p msp_consolidator < database/schema.sql
```

Puis appliquez les migrations dans l'ordre :

```bash
mysql -u msp_user -p msp_consolidator < database/migrations/001_...sql
mysql -u msp_user -p msp_consolidator < database/migrations/002_...sql
# ... répéter pour chaque fichier dans database/migrations/
```

---

## 4. Configurer l'application

### 4.1 Configuration générale — `config/app.php`

```php
return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'msp_consolidator',
        'username' => 'msp_user',
        'password' => 'votre_mot_de_passe',
        'charset'  => 'utf8mb4',
    ],
    'timezone' => 'Europe/Paris',
    'debug'    => false,   // Mettre true uniquement en développement
];
```

### 4.2 Configuration des fournisseurs — `config/providers.php`

Ce fichier contient les identifiants API de chaque fournisseur. Voir la documentation de chaque fournisseur :

- [ESET](providers/eset.md)
- [Be-Cloud / Microsoft 365](providers/becloud.md)
- [NinjaOne](providers/ninjaone.md)
- [Infomaniak](providers/infomaniak.md)

---

## 5. Configurer le serveur web

### Apache (`.htaccess` déjà inclus dans `public/`)

Pointez le `DocumentRoot` de votre virtualhost vers le dossier `public/` :

```apache
<VirtualHost *:80>
    ServerName msp.local
    DocumentRoot /chemin/vers/MSP-Consolidator/public
    <Directory /chemin/vers/MSP-Consolidator/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Activez `mod_rewrite` si ce n'est pas déjà fait :

```bash
a2enmod rewrite
systemctl restart apache2
```

### WAMP / XAMPP

Dans WAMP, créez un VirtualHost via l'interface ou modifiez directement `httpd-vhosts.conf` pour pointer vers `public/`.

---

## 6. Premier démarrage

1. Ouvrez l'application dans votre navigateur
2. Allez dans **Paramètres → Connexions** pour vérifier que les connexions fournisseurs sont bien reconnues
3. Depuis la page d'un fournisseur, lancez une première synchronisation via le bouton **Sync maintenant**
4. Allez dans **Mapping** pour associer vos clients internes aux clients fournisseurs

---

## 7. Automatiser la synchronisation (cron)

Les scripts CLI dans `scripts/` permettent de synchroniser automatiquement les données sans passer par l'interface web.

### Scripts disponibles

| Script | Rôle |
|---|---|
| `scripts/sync_all.php` | Synchronise **tous** les fournisseurs actifs |
| `scripts/sync_eset.php` | ESET uniquement |
| `scripts/sync_becloud.php` | Be-Cloud uniquement |
| `scripts/sync_ninjaone.php` | NinjaOne uniquement |
| `scripts/sync_infomaniak.php` | Infomaniak uniquement |

### Utilisation manuelle

```bash
# Tous les fournisseurs
php scripts/sync_all.php

# Un seul fournisseur
php scripts/sync_all.php --provider=becloud

# Plusieurs fournisseurs
php scripts/sync_all.php --provider=eset,ninjaone
```

### Configuration cron (Linux / serveur)

Éditez la crontab de l'utilisateur web (`www-data` sous Debian/Ubuntu) :

```bash
sudo crontab -u www-data -e
```

Ajoutez une ligne pour synchroniser toutes les heures :

```
0 * * * * php /var/www/MSP-Consolidator/scripts/sync_all.php >> /var/log/msp-sync.log 2>&1
```

Ou par fournisseur à des fréquences différentes :

```
0  * * * * php /var/www/MSP-Consolidator/scripts/sync_eset.php       >> /var/log/msp-sync.log 2>&1
15 * * * * php /var/www/MSP-Consolidator/scripts/sync_becloud.php     >> /var/log/msp-sync.log 2>&1
30 * * * * php /var/www/MSP-Consolidator/scripts/sync_ninjaone.php    >> /var/log/msp-sync.log 2>&1
45 * * * * php /var/www/MSP-Consolidator/scripts/sync_infomaniak.php  >> /var/log/msp-sync.log 2>&1
```

> **Note :** Les scripts refusent d'être exécutés via un navigateur (`PHP_SAPI !== 'cli'`). Ils retournent le code de sortie `0` en cas de succès, `1` en cas d'erreur (compatible avec les outils de monitoring cron).

### Planificateur de tâches Windows (WAMP / environnement local)

Si l'application tourne sous Windows avec WAMP, utilisez le Planificateur de tâches Windows :

1. Ouvrez **Planificateur de tâches** → Créer une tâche de base
2. Déclencheur : Toutes les heures
3. Action : Démarrer un programme
   - Programme : `C:\wamp64\bin\php\phpX.X.X\php.exe`
   - Arguments : `C:\wamp64\www\MSP-Consolidator\scripts\sync_all.php`

---

## 8. Mises à jour

```bash
git pull origin main
composer install --no-dev

# Appliquer les nouvelles migrations si présentes
mysql -u msp_user -p msp_consolidator < database/migrations/XXX_....sql
```

> Consultez l'historique Git (`git log --oneline`) avant chaque mise à jour pour vérifier si de nouveaux fichiers de migration sont présents dans `database/migrations/`.
