-- ============================================================
-- Migration 009 : Be-Cloud — licences M365 + provider_instance_id
-- ============================================================

-- 1. Ajouter provider_instance_id à be_cloud_subscriptions
--    (requis pour appeler GET /v1/Customers/{id}/licenses?providerInstanceId=)
ALTER TABLE `be_cloud_subscriptions`
    ADD COLUMN `provider_instance_id` VARCHAR(36) NULL
        COMMENT 'ID instance fournisseur CloudCockpit, requis pour endpoint licenses'
        AFTER `auto_renewal`;

-- 2. Table be_cloud_licenses (licences M365/cloud par customer)
CREATE TABLE IF NOT EXISTS `be_cloud_licenses` (
    `id`                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `connection_id`        SMALLINT UNSIGNED NOT NULL,
    `be_cloud_customer_id` VARCHAR(36)       NOT NULL COMMENT 'UUID customer Be-Cloud',
    `sku_id`               VARCHAR(100)      NOT NULL COMMENT 'Identifiant SKU licence',
    `name`                 VARCHAR(255)      NULL     COMMENT 'Nom lisible de la licence',
    `total_licenses`       INT               NOT NULL DEFAULT 0,
    `consumed_licenses`    INT               NOT NULL DEFAULT 0,
    `available_licenses`   INT               NOT NULL DEFAULT 0,
    `suspended_licenses`   INT               NOT NULL DEFAULT 0,
    `is_selected`          TINYINT(1)        NOT NULL DEFAULT 0,
    `raw_data`             JSON              NULL,
    `last_sync_at`         TIMESTAMP         NULL DEFAULT NULL,
    `created_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_becloud_license` (`connection_id`, `be_cloud_customer_id`, `sku_id`),
    INDEX `idx_becloud_customer_lic` (`be_cloud_customer_id`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Licences M365/cloud Be-Cloud par customer (endpoint /licenses)';
