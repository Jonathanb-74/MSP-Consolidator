-- Migration 005 : Table des équipements NinjaOne individuels
--
-- Stocke chaque device retourné par /v2/devices pour permettre
-- la consultation détaillée depuis le Récap Licences.

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
    `created_at`         TIMESTAMP         NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP         NOT NULL  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ninja_device` (`connection_id`, `ninjaone_device_id`),
    INDEX `idx_ninja_device_org` (`connection_id`, `ninjaone_org_id`),
    FOREIGN KEY (`connection_id`) REFERENCES `provider_connections`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
