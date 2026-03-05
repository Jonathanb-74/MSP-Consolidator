-- ============================================================
-- Migration 002 : Intégration fournisseur Be-Cloud
-- Tables be_cloud_customers et be_cloud_subscriptions
-- ============================================================

-- 1. Customers Be-Cloud (équivalent eset_companies)
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

-- 2. Subscriptions Be-Cloud (équivalent eset_licenses)
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

-- NOTE : Après ce SQL, exécuter :
--   php database/migrations/002_migrate_becloud.php
