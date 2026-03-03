-- ============================================================
-- MSP Consolidator — Schéma SQL
-- MySQL 8.0+ / MariaDB 10.4+
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
    `client_number` VARCHAR(50)     NOT NULL COMMENT 'Clé métier (unique par structure)',
    `structure_id`  TINYINT UNSIGNED NOT NULL,
    `name`          VARCHAR(255)    NOT NULL,
    `email`         VARCHAR(255)    DEFAULT NULL,
    `phone`         VARCHAR(50)     DEFAULT NULL,
    `address`       TEXT            DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`structure_id`) REFERENCES `structures`(`id`),
    UNIQUE KEY `uq_client_structure` (`client_number`, `structure_id`),
    INDEX `idx_structure` (`structure_id`),
    INDEX `idx_active`    (`is_active`)
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

CREATE TABLE IF NOT EXISTS `client_provider_mappings` (
    `id`                   INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `client_id`            INT UNSIGNED     NOT NULL,
    `provider_id`          TINYINT UNSIGNED NOT NULL,
    `provider_client_id`   VARCHAR(255)     NOT NULL COMMENT 'ESET UUID, NinjaOne orgId, etc.',
    `provider_client_name` VARCHAR(255)     DEFAULT NULL,
    `mapping_method`       ENUM('manual','client_number','name_match') NOT NULL DEFAULT 'manual',
    `is_confirmed`         TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'Validé manuellement',
    `match_score`          TINYINT UNSIGNED DEFAULT NULL COMMENT 'Score similarité 0-100 (name_match)',
    `notes`                TEXT             DEFAULT NULL,
    `created_at`           TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`)   REFERENCES `clients`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
    UNIQUE KEY `uq_provider_client` (`provider_id`, `provider_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id`              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `provider_id`     TINYINT UNSIGNED NOT NULL,
    `started_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at`     TIMESTAMP        NULL DEFAULT NULL,
    `status`          ENUM('running','success','partial','error','cancelled') NOT NULL DEFAULT 'running',
    `records_fetched` INT              NOT NULL DEFAULT 0,
    `records_created` INT              NOT NULL DEFAULT 0,
    `records_updated` INT              NOT NULL DEFAULT 0,
    `error_message`   TEXT             DEFAULT NULL,
    `triggered_by`    ENUM('cron','manual','web') NOT NULL DEFAULT 'cron',
    FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`),
    INDEX `idx_provider_status` (`provider_id`, `status`),
    INDEX `idx_started_at`      (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE ESET
-- ============================================================

CREATE TABLE IF NOT EXISTS `eset_companies` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `eset_company_id`   VARCHAR(255) NOT NULL UNIQUE COMMENT 'UUID EMA2',
    `name`              VARCHAR(255) NOT NULL,
    `company_type_id`   TINYINT UNSIGNED DEFAULT NULL,
    `status_id`         TINYINT UNSIGNED DEFAULT NULL,
    `custom_identifier` VARCHAR(255) DEFAULT NULL COMMENT 'Peut correspondre à clients.client_number',
    `email`             VARCHAR(255) DEFAULT NULL,
    `vat_id`            VARCHAR(100) DEFAULT NULL,
    `description`       TEXT         DEFAULT NULL,
    `parent_eset_id`    VARCHAR(255) DEFAULT NULL,
    `raw_data`          JSON         DEFAULT NULL,
    `last_sync_at`      TIMESTAMP    NULL DEFAULT NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    `state`              VARCHAR(50)  DEFAULT NULL COMMENT 'VALID, EXPIRED, etc.',
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
    ('wasabi',     'Wasabi',                   0),
    ('veeam',      'Veeam',                    0),
    ('infomaniak', 'Infomaniak',               0);
