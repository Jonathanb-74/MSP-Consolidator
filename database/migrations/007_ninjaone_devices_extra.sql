-- Migration 007 : Ajout Marque, Modèle, Dernier utilisateur dans ninjaone_devices

ALTER TABLE `ninjaone_devices`
    ADD COLUMN `manufacturer`      VARCHAR(100) NULL AFTER `os_name`,
    ADD COLUMN `model`             VARCHAR(150) NULL AFTER `manufacturer`,
    ADD COLUMN `last_logged_user`  VARCHAR(255) NULL AFTER `model`;
