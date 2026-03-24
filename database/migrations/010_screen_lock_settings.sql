-- ============================================================
-- Migration 010 : Paramètres de blocage écran trop petit
-- ============================================================

INSERT IGNORE INTO `app_settings` (`key`, `value`, `label`, `description`, `type`) VALUES
    ('screen_lock_enabled',
     '0',
     'Blocage petits écrans',
     'Affiche un message bloquant lorsque la largeur du navigateur est inférieure au seuil défini.',
     'boolean'),

    ('screen_lock_min_width',
     '992',
     'Largeur minimale (px)',
     'En dessous de cette largeur de fenêtre (en pixels), l''accès est bloqué si le blocage est activé.',
     'integer'),

    ('screen_lock_message',
     'Cette application est conçue pour être utilisée sur un écran plus large. Veuillez vous connecter depuis un ordinateur ou agrandir la fenêtre de votre navigateur.',
     'Message affiché sur petit écran',
     'Texte affiché à l''utilisateur lorsque son écran est trop petit.',
     'string');
