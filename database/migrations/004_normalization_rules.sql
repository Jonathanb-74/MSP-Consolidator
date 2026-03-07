-- Migration 004 : Règles de normalisation des noms (auto-mapping)
--
-- Table utilisée par App\Core\NameNormalizer pour supprimer des fragments
-- de noms avant la comparaison par similarité.
--
-- Types :
--   legal_form → supprimé en tant que mot entier (\b...\b)
--   custom     → supprimé en tant que sous-chaîne exacte (ex: " - LTI")

CREATE TABLE IF NOT EXISTS `normalization_rules` (
    `id`         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `value`      VARCHAR(100)      NOT NULL                  COMMENT 'Chaîne à supprimer (casse insensible)',
    `type`       ENUM('legal_form','custom') NOT NULL DEFAULT 'custom',
    `active`     TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_norm_value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formes juridiques françaises (anciennement codées en dur dans NameNormalizer)
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
