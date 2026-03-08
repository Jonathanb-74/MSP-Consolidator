-- ============================================================
-- MSP Consolidator — Schéma SQL complet
-- MySQL 8.0+ / MariaDB 10.4+
-- Intègre toutes les migrations 001 → 008
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================
-- TABLES CORE (provider-agnostic)
-- ============================================================

CREATE TABLE IF NOT EXISTS `structures` (
    `id`   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20)  NOT NULL UNIQUE COMMENT 'FCI, LTI, LNI, MACSHOP',
    `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clients` (
    `id`            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `client_number` VARCHAR(50)     NOT NULL UNIQUE COMMENT 'Clé métier',
    `name`          VARCHAR(255)    NOT NULL,
    `email`         VARCHAR(255)    DEFAULT NULL,
    `phone`         VARCHAR(50)     DEFAULT NULL,
    `address`       TEXT            DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(50)  NOT NULL UNIQUE,
    `color`         VARCHAR(7)   NOT NULL DEFAULT '#6c757d' COMMENT 'Couleur hexadécimale (#rrggbb)',
    `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_tags` (
    `client_id` INT UNSIGNED NOT NULL,
    `tag_id`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`client_id`, `tag_id`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`)    REFERENCES `tags`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `providers` (
    `id`           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`         VARCHAR(50)  NOT NULL UNIQUE COMMENT 'eset, ninjaone, wasabi, veeam, infomaniak',
    `name`         VARCHAR(100) NOT NULL,
    `is_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
    `last_sync_at` TIMESTAMP    NULL DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 001 : Multi-connexions par fournisseur
CREATE TABLE IF NOT EXISTS `provider_connections` (
    `id`           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id`  TINYINT UNSIGNED  NOT NULL,
    `config_key`   VARCHAR(50)       NOT NULL COMMENT 'Clé dans config/providers.php',
    `name`         VARCHAR(100)      NOT NULL COMMENT 'Nom affiché (ex: Console FCI)',
    `is_enabled`   TINYINT(1)        NOT NULL DEFAULT 1,
    `last_sync_at` TIMESTAMP         NULL DEFAULT NULL,
    `sync_status`  ENUM('idle','running','success','error') NOT NULL DEFAULT 'idle',
    `created_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_connection_key` (`provider_id`, `config_key`),
    FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_provider_mappings` (
    `id`                   INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    `client_id`            INT UNSIGNED      NOT NULL,
    `provider_id`          TINYINT UNSIGNED  NOT NULL,
    `connection_id`        SMALLINT UNSIGNED NULL COMMENT 'Migration 001 — connexion spécifique',
    `provider_client_id`   VARCHAR(255)      NOT NULL COMMENT 'ESET UUID, NinjaOne orgId, etc.',
    `provider_client_name` VARCHAR(255)      DEFAULT NULL,
    `mapping_method`       ENUM('manual','client_number','name_match') NOT NULL DEFAULT 'manual',
    `is_confirmed`         TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Validé manuellement',
    `match_score`          TINYINT UNSIGNED  DEFAULT NULL COMMENT 'Score similarité 0-100 (name_match)',
    `notes`                TEXT              DEFAULT NULL,
    `created_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`)   REFERENCES `clients`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
    UNIQUE KEY `uq_provider_client` (`provider_id`, `provider_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id`              INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    `provider_id`     TINYINT UNSIGNED  NOT NULL,
    `connection_id`   SMALLINT UNSIGNED NULL COMMENT 'Migration 001 — connexion concernée',
    `started_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at`     TIMESTAMP         NULL DEFAULT NULL,
    `status`          ENUM('running','success','partial','error','cancelled') NOT NULL DEFAULT 'running',
    `records_fetched` INT               NOT NULL DEFAULT 0,
    `records_created` INT               NOT NULL DEFAULT 0,
    `records_updated` INT               NOT NULL DEFAULT 0,
    `error_message`   TEXT              DEFAULT NULL,
    `triggered_by`    ENUM('cron','manual','web') NOT NULL DEFAULT 'cron',
    FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
    INDEX `idx_provider_status` (`provider_id`, `status`),
    INDEX `idx_started_at`      (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE ESET
-- ============================================================

CREATE TABLE IF NOT EXISTS `eset_companies` (
    `id`                INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    `connection_id`     SMALLINT UNSIGNED NULL COMMENT 'Migration 001 — console ESET source',
    `eset_company_id`   VARCHAR(255)      NOT NULL UNIQUE COMMENT 'UUID EMA2',
    `name`              VARCHAR(255)      NOT NULL,
    `company_type_id`   TINYINT UNSIGNED  DEFAULT NULL,
    `status_id`         TINYINT UNSIGNED  DEFAULT NULL,
    `custom_identifier` VARCHAR(255)      DEFAULT NULL COMMENT 'Peut correspondre à clients.client_number',
    `email`             VARCHAR(255)      DEFAULT NULL,
    `vat_id`            VARCHAR(100)      DEFAULT NULL,
    `description`       TEXT              DEFAULT NULL,
    `parent_eset_id`    VARCHAR(255)      DEFAULT NULL,
    `raw_data`          JSON              DEFAULT NULL,
    `last_sync_at`      TIMESTAMP         NULL DEFAULT NULL,
    `created_at`        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_connection`        (`connection_id`),
    INDEX `idx_custom_identifier` (`custom_identifier`),
    INDEX `idx_status`            (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `eset_licenses` (
    `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `eset_company_id`    VARCHAR(255) NOT NULL,
    `public_license_key` VARCHAR(50)  NOT NULL UNIQUE,
    `product_code`       VARCHAR(100) DEFAULT NULL,
    `product_name`       VARCHAR(255) DEFAULT NULL,
    `quantity`           INT          NOT NULL DEFAULT 0 COMMENT 'Sièges total',
    `usage_count`        INT          NOT NULL DEFAULT 0 COMMENT 'Sièges utilisés',
    `state`              VARCHAR(50)  DEFAULT NULL COMMENT '0=Error,1=Normal,2=Obsolete,3=Suspended,4=Warning',
    `expiration_date`    DATE         DEFAULT NULL,
    `is_trial`           TINYINT(1)   NOT NULL DEFAULT 0,
    `raw_data`           JSON         DEFAULT NULL,
    `last_sync_at`       TIMESTAMP    NULL DEFAULT NULL,
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_eset_company`  (`eset_company_id`),
    INDEX `idx_expiry`        (`expiration_date`),
    INDEX `idx_state`         (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE BE-CLOUD (Migration 002)
-- ============================================================

CREATE TABLE IF NOT EXISTS `be_cloud_customers` (
    `id`                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `connection_id`        SMALLINT UNSIGNED NOT NULL,
    `be_cloud_customer_id` VARCHAR(36)       NOT NULL COMMENT 'UUID Be-Cloud (id)',
    `name`                 VARCHAR(255)      NOT NULL,
    `internal_identifier`  VARCHAR(255)      NULL     COMMENT 'Référence interne → client_number pour auto-mapping',
    `email`                VARCHAR(255)      NULL,
    `tax_id`               VARCHAR(100)      NULL,
    `reseller_id`          VARCHAR(36)       NULL,
    `raw_data`             JSON              NULL,
    `last_sync_at`         TIMESTAMP         NULL DEFAULT NULL,
    `created_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_becloud_customer_conn` (`connection_id`, `be_cloud_customer_id`),
    INDEX `idx_internal_identifier` (`internal_identifier`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `be_cloud_subscriptions` (
    `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `be_cloud_customer_id`     VARCHAR(36)  NOT NULL COMMENT 'UUID customer Be-Cloud',
    `be_cloud_subscription_id` VARCHAR(36)  NOT NULL COMMENT 'UUID subscription Be-Cloud',
    `subscription_name`        VARCHAR(255) NULL,
    `offer_name`               VARCHAR(255) NULL,
    `offer_id`                 VARCHAR(255) NULL,
    `offer_type`               VARCHAR(50)  NULL COMMENT 'License, SoftwareSubscription, AzurePlan, etc.',
    `status`                   VARCHAR(50)  NULL COMMENT 'Active, Suspended, Deleted, etc.',
    `quantity`                 INT          NOT NULL DEFAULT 0 COMMENT 'Total licences souscrites',
    `assigned_licenses`        INT          NOT NULL DEFAULT 0 COMMENT 'Licences assignées',
    `start_date`               DATE         NULL,
    `end_date`                 DATE         NULL COMMENT 'Date de fin / renouvellement',
    `billing_frequency`        VARCHAR(50)  NULL COMMENT 'Monthly, Annual, etc.',
    `term_duration`            VARCHAR(50)  NULL COMMENT 'P1M, P1Y, P3Y, etc.',
    `is_trial`                 TINYINT(1)   NOT NULL DEFAULT 0,
    `auto_renewal`             TINYINT(1)   NOT NULL DEFAULT 0,
    `raw_data`                 JSON         NULL,
    `last_sync_at`             TIMESTAMP    NULL DEFAULT NULL,
    `created_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_becloud_sub` (`be_cloud_subscription_id`),
    INDEX `idx_becloud_customer` (`be_cloud_customer_id`),
    INDEX `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE NINJAONE (Migrations 003, 005, 007)
-- ============================================================

CREATE TABLE IF NOT EXISTS `ninjaone_organizations` (
    `id`               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `connection_id`    SMALLINT UNSIGNED NOT NULL,
    `ninjaone_org_id`  INT UNSIGNED      NOT NULL COMMENT 'ID entier NinjaOne',
    `name`             VARCHAR(255)      NOT NULL,
    `description`      VARCHAR(500)      NULL,
    `rmm_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Équipements groupe RMM',
    `vmm_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Équipements groupe VMM (no license)',
    `nms_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Équipements groupe NMS',
    `mdm_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Équipements groupe MDM',
    `cloud_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Équipements Cloud Monitoring (no license)',
    `raw_data`         JSON              NULL,
    `last_sync_at`     TIMESTAMP         NULL,
    `created_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ninja_org_conn` (`connection_id`, `ninjaone_org_id`),
    INDEX `idx_ninja_name` (`name`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ninjaone_devices` (
    `id`                 INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `connection_id`      SMALLINT UNSIGNED NOT NULL,
    `ninjaone_device_id` INT UNSIGNED      NOT NULL  COMMENT 'id dans l''API NinjaOne',
    `ninjaone_org_id`    INT UNSIGNED      NOT NULL  COMMENT 'organizationId dans l''API',
    `display_name`       VARCHAR(255)      NOT NULL  DEFAULT '',
    `dns_name`           VARCHAR(255)      NULL,
    `node_class`         VARCHAR(50)       NOT NULL,
    `node_group`         ENUM('RMM','NMS','MDM','VMM','CLOUD_MONITORING','OTHER') NOT NULL DEFAULT 'OTHER',
    `last_contact`       DATETIME          NULL,
    `is_online`          TINYINT(1)        NOT NULL  DEFAULT 0,
    `os_name`            VARCHAR(150)      NULL,
    `manufacturer`       VARCHAR(100)      NULL      COMMENT 'Migration 007',
    `model`              VARCHAR(150)      NULL      COMMENT 'Migration 007',
    `last_logged_user`   VARCHAR(255)      NULL      COMMENT 'Migration 007',
    `created_at`         TIMESTAMP         NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP         NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ninja_device` (`connection_id`, `ninjaone_device_id`),
    INDEX `idx_ninja_device_org` (`connection_id`, `ninjaone_org_id`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UTILITAIRES (Migrations 004, 006)
-- ============================================================

CREATE TABLE IF NOT EXISTS `normalization_rules` (
    `id`         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `value`      VARCHAR(100)      NOT NULL                  COMMENT 'Chaîne à supprimer (casse insensible)',
    `type`       ENUM('legal_form','custom') NOT NULL DEFAULT 'custom',
    `active`     TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_norm_value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_settings` (
    `key`         VARCHAR(100)  NOT NULL,
    `value`       VARCHAR(500)  NOT NULL DEFAULT '',
    `label`       VARCHAR(255)  NOT NULL DEFAULT '',
    `description` TEXT          NULL,
    `type`        ENUM('string','integer','boolean') NOT NULL DEFAULT 'string',
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES INITIALES (seed)
-- ============================================================

INSERT IGNORE INTO `structures` (`code`, `name`) VALUES
    ('FCI',     'FCI'),
    ('LTI',     'LTI'),
    ('LNI',     'LNI'),
    ('MACSHOP', 'MACSHOP');

INSERT IGNORE INTO `providers` (`code`, `name`, `is_enabled`) VALUES
    ('eset',       'ESET MSP Administrator 2', 1),
    ('ninjaone',   'NinjaOne',                 0),
    ('becloud',    'Be-Cloud',                 0),
    ('wasabi',     'Wasabi',                   0),
    ('veeam',      'Veeam',                    0),
    ('infomaniak', 'Infomaniak',               0);

INSERT IGNORE INTO `normalization_rules` (`value`, `type`) VALUES
    ('selarl', 'legal_form'),
    ('sasu',   'legal_form'),
    ('sarl',   'legal_form'),
    ('earl',   'legal_form'),
    ('scop',   'legal_form'),
    ('eurl',   'legal_form'),
    ('sci',    'legal_form'),
    ('snc',    'legal_form'),
    ('scp',    'legal_form'),
    ('sca',    'legal_form'),
    ('sel',    'legal_form'),
    ('gie',    'legal_form'),
    ('sas',    'legal_form'),
    ('sa',     'legal_form');

INSERT IGNORE INTO `app_settings` (`key`, `value`, `label`, `description`, `type`) VALUES
    ('device_active_days', '2',
     'Seuil d''inactivité équipements (jours)',
     'Nombre de jours sans contact au-delà duquel un équipement est affiché comme inactif. Utilisé pour NinjaOne et ESET.',
     'integer');

-- ============================================================
-- INFOMANIAK (migration 008)
-- ============================================================

CREATE TABLE IF NOT EXISTS `infomaniak_accounts` (
    `id`                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `connection_id`         SMALLINT UNSIGNED NOT NULL,
    `infomaniak_account_id` INT UNSIGNED      NOT NULL COMMENT 'id retourné par /1/accounts',
    `name`                  VARCHAR(255)      NOT NULL,
    `legal_entity_type`     VARCHAR(50)       NULL COMMENT 'individual, company',
    `type`                  VARCHAR(50)       NULL COMMENT 'partner_limited, etc.',
    `is_customer`           TINYINT(1)        NOT NULL DEFAULT 0,
    `raw_data`              JSON              NULL,
    `last_sync_at`          TIMESTAMP         NULL DEFAULT NULL,
    `created_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_infomaniak_account_conn` (`connection_id`, `infomaniak_account_id`),
    INDEX `idx_infomaniak_name` (`name`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infomaniak_products` (
    `id`                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `connection_id`         SMALLINT UNSIGNED NOT NULL,
    `infomaniak_product_id` INT UNSIGNED      NOT NULL COMMENT 'id retourné par /1/products',
    `infomaniak_account_id` INT UNSIGNED      NOT NULL COMMENT 'account_id du produit',
    `service_id`            INT               NULL,
    `service_name`          VARCHAR(100)      NULL COMMENT 'domain, hosting, mail, etc.',
    `customer_name`         VARCHAR(255)      NULL,
    `internal_name`         VARCHAR(255)      NULL,
    `expired_at`            INT               NULL COMMENT 'Timestamp Unix expiration',
    `is_trial`              TINYINT(1)        NOT NULL DEFAULT 0,
    `is_free`               TINYINT(1)        NOT NULL DEFAULT 0,
    `description`           TEXT              NULL,
    `raw_data`              JSON              NULL,
    `last_sync_at`          TIMESTAMP         NULL DEFAULT NULL,
    `created_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_infomaniak_product_conn` (`connection_id`, `infomaniak_product_id`),
    INDEX `idx_infomaniak_product_account` (`infomaniak_account_id`),
    INDEX `idx_infomaniak_service_name` (`service_name`),
    INDEX `idx_infomaniak_expired_at` (`expired_at`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
