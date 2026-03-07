-- ============================================================
-- Migration 003 : Ajout table ninjaone_organizations
-- Provider NinjaOne — comptes d'équipements par groupe de licence
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
