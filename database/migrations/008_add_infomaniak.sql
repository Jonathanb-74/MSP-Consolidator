-- Migration 008 : Connecteur Infomaniak
--
-- Table infomaniak_accounts  — un compte Infomaniak = un client potentiel
-- Table infomaniak_products  — un produit = une ligne de service par compte

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
