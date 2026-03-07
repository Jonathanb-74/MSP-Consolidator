<?php

namespace App\Core;

class NameNormalizer
{
    /** Règles chargées depuis la DB (cache statique par requête). */
    private static ?array $cachedRules = null;

    /** Fallback si la table normalization_rules n'existe pas encore. */
    private const FALLBACK_LEGAL_FORMS = [
        'selarl','sasu','sarl','earl','scop','eurl',
        'sci','snc','scp','sca','sel','gie','sas','sa',
    ];

    private const ACCENT_MAP = [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    /**
     * Normalise un nom pour la comparaison par similarité.
     *
     * Ordre :
     *   1. Minuscules
     *   2. Accents → ASCII
     *   3. Suppression des règles actives (formes juridiques en mot entier,
     *      exclusions personnalisées en sous-chaîne)
     *   4. Suppression de la ponctuation résiduelle
     *   5. Collapse des espaces
     */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, self::ACCENT_MAP);

        foreach (self::loadRules() as $rule) {
            $val = mb_strtolower($rule['value']);
            if ($rule['type'] === 'legal_form') {
                $name = preg_replace('/\b' . preg_quote($val, '/') . '\b/', '', $name);
            } else {
                // Exclusion personnalisée : sous-chaîne exacte (casse déjà normalisée)
                $name = str_replace($val, '', $name);
            }
        }

        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name;
    }

    /**
     * Invalide le cache (à appeler après modification des règles en DB).
     */
    public static function clearCache(): void
    {
        self::$cachedRules = null;
    }

    // ── Interne ─────────────────────────────────────────────────────────────

    private static function loadRules(): array
    {
        if (self::$cachedRules !== null) {
            return self::$cachedRules;
        }

        try {
            $db = Database::getInstance();
            self::$cachedRules = $db->fetchAll(
                "SELECT value, type FROM normalization_rules WHERE active = 1 ORDER BY type, CHAR_LENGTH(value) DESC, value"
            );
        } catch (\Throwable) {
            // Table absente (migration non encore exécutée) → fallback
            self::$cachedRules = array_map(
                fn($v) => ['value' => $v, 'type' => 'legal_form'],
                self::FALLBACK_LEGAL_FORMS
            );
        }

        return self::$cachedRules;
    }
}
