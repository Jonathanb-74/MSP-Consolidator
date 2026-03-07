-- Migration 006 : Paramètres applicatifs généraux
--
-- Table clé-valeur pour les réglages de l'application.
-- Utilisée par App\Core\AppSettings (cache statique par requête).

CREATE TABLE IF NOT EXISTS `app_settings` (
    `key`         VARCHAR(100)  NOT NULL,
    `value`       VARCHAR(500)  NOT NULL DEFAULT '',
    `label`       VARCHAR(255)  NOT NULL DEFAULT '',
    `description` TEXT          NULL,
    `type`        ENUM('string','integer','boolean') NOT NULL DEFAULT 'string',
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `app_settings` (`key`, `value`, `label`, `description`, `type`) VALUES
    ('device_active_days', '2',
     'Seuil d''inactivité équipements (jours)',
     'Nombre de jours sans contact au-delà duquel un équipement est affiché comme inactif. Utilisé pour NinjaOne et ESET.',
     'integer');
