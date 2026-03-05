<?php

namespace App\Core;

/**
 * Helper pour accéder à la configuration des fournisseurs (config/providers.php).
 *
 * Supporte deux formats :
 *   - Format plat (héritage) : 'eset' => ['username' => ..., 'password' => ...]
 *   - Format multi-connexions : 'eset' => [['key' => 'principale', 'username' => ...], [...]]
 *
 * Les deux formats sont normalisés en tableau de connexions.
 */
class ProviderConfig
{
    private static ?array $config = null;

    private static function load(): array
    {
        if (self::$config === null) {
            self::$config = require APP_ROOT . '/config/providers.php';
        }
        return self::$config;
    }

    /**
     * Retourne la liste des connexions normalisées pour un code de fournisseur.
     *
     * @param string $code  Ex: 'eset', 'ninjaone'
     * @return array[]      Tableau de connexions, chacune avec au moins ['key', 'name']
     */
    public static function getConnections(string $code): array
    {
        $raw = self::load()[$code] ?? [];
        if (empty($raw)) {
            return [];
        }
        return self::normalize($raw);
    }

    /**
     * Retourne une connexion spécifique par code fournisseur + config_key.
     */
    public static function findConnection(string $code, string $configKey): ?array
    {
        foreach (self::getConnections($code) as $conn) {
            if (($conn['key'] ?? '') === $configKey) {
                return $conn;
            }
        }
        return null;
    }

    /**
     * Retourne la première connexion active pour un fournisseur (défaut/fallback).
     */
    public static function firstConnection(string $code): ?array
    {
        foreach (self::getConnections($code) as $conn) {
            if ($conn['enabled'] ?? true) {
                return $conn;
            }
        }
        return null;
    }

    /**
     * Retourne tous les codes de fournisseurs définis dans la config.
     */
    public static function getAllCodes(): array
    {
        return array_keys(self::load());
    }

    /**
     * Normalise un tableau brut en liste de connexions.
     * - Format plat     → [['key' => 'default', 'name' => 'Connexion principale', ...]]
     * - Format tableau  → tel quel (vérifie et complète les clés manquantes)
     */
    private static function normalize(array $raw): array
    {
        // Détecter le format plat : les clés sont des strings (username, password, etc.)
        // vs le format tableau : les clés sont des entiers (0, 1, 2...)
        $keys = array_keys($raw);
        if ($keys !== array_filter($keys, 'is_int')) {
            // Format plat → une seule connexion
            return [array_merge(['key' => 'default', 'name' => 'Connexion principale'], $raw)];
        }

        // Format tableau → valider chaque connexion
        return array_map(function (array $conn, int $i): array {
            return array_merge([
                'key'  => "connexion_{$i}",
                'name' => "Connexion " . ($i + 1),
            ], $conn);
        }, $raw, array_keys($raw));
    }
}
