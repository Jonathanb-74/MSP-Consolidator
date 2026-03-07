<?php

namespace App\Core;

/**
 * Paramètres applicatifs généraux stockés en base de données.
 *
 * Utilisation :
 *   AppSettings::get('device_active_days', 2)  → valeur castée selon le type
 *   AppSettings::set('device_active_days', '5')
 *   AppSettings::clearCache()
 */
class AppSettings
{
    private static ?array $cache = null;

    /**
     * Récupère la valeur d'un paramètre, castée selon son type DB.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::loadAll();

        if (!array_key_exists($key, $all)) {
            return $default;
        }

        $row = $all[$key];

        return match ($row['type']) {
            'integer' => (int)$row['value'],
            'boolean' => filter_var($row['value'], FILTER_VALIDATE_BOOLEAN),
            default   => $row['value'],
        };
    }

    /**
     * Retourne tous les paramètres indexés par clé (tableau complet avec label/type/description).
     */
    public static function all(): array
    {
        return self::loadAll();
    }

    /**
     * Met à jour la valeur d'un paramètre existant.
     */
    public static function set(string $key, string $value): void
    {
        Database::getInstance()->execute(
            "UPDATE app_settings SET value = ? WHERE `key` = ?",
            [$value, $key]
        );
        self::$cache = null;
    }

    /**
     * Invalide le cache (à appeler après modification).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    // ── Interne ─────────────────────────────────────────────────────────────

    private static function loadAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $rows = Database::getInstance()->fetchAll(
                "SELECT `key`, `value`, `label`, `description`, `type` FROM app_settings ORDER BY `key`"
            );
            self::$cache = array_column($rows, null, 'key');
        } catch (\Throwable) {
            // Table absente (migration non encore exécutée)
            self::$cache = [];
        }

        return self::$cache;
    }
}
