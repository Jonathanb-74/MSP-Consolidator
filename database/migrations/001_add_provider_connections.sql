-- ============================================================
-- Migration 001 : Ajout table provider_connections
-- Multi-connexions par fournisseur (ex : 2 consoles ESET)
-- ============================================================

-- 1. Nouvelle table provider_connections
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

-- 2. Ajouter connection_id dans client_provider_mappings (nullable d'abord)
ALTER TABLE `client_provider_mappings`
    ADD COLUMN `connection_id` SMALLINT UNSIGNED NULL AFTER `provider_id`;

-- 3. Ajouter connection_id dans sync_logs (nullable d'abord)
ALTER TABLE `sync_logs`
    ADD COLUMN `connection_id` SMALLINT UNSIGNED NULL AFTER `provider_id`;

-- 4. Ajouter connection_id dans eset_companies (tracking quelle console a sync)
ALTER TABLE `eset_companies`
    ADD COLUMN `connection_id` SMALLINT UNSIGNED NULL AFTER `id`,
    ADD INDEX `idx_connection` (`connection_id`);

-- NOTE : Après avoir exécuté ce SQL, lancer le script PHP de migration :
--   php database/migrations/001_migrate_connections.php
-- Ce script crée les lignes provider_connections depuis config/providers.php
-- et met à jour les FK connection_id dans les tables existantes.
