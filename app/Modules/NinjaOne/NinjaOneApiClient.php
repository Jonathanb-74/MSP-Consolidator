<?php

namespace App\Modules\NinjaOne;

use App\Core\ApiClient;
use App\Modules\NinjaOne\Exception\NinjaOneAuthException;
use RuntimeException;

/**
 * Client API pour NinjaOne (API publique v2).
 *
 * Base URL : https://app.ninjarmm.com (ou eu.ninjarmm.com)
 * Endpoints utilisés :
 *   GET /api/v2/organizations  — liste des organisations (paginée)
 *   GET /api/v2/devices        — liste de tous les équipements (paginée)
 *
 * Pagination : curseur via paramètre `after` (= dernier id retourné)
 *              réponse = tableau direct (pas enveloppé dans 'items')
 * Auth       : Bearer token (OAuth2 client_credentials)
 * Rate limit : 10 req / 10 min pour les list APIs → pageSize=1000
 */
class NinjaOneApiClient extends ApiClient
{
    private NinjaOneTokenCache $tokenCache;

    private const PAGE_SIZE = 1000;

    // 200ms entre pages (rate limit 10 req/10min)
    private const RATE_LIMIT_SLEEP_US = 200_000;

    public function __construct(array $credentials, NinjaOneTokenCache $tokenCache)
    {
        $this->baseUrl            = rtrim($credentials['base_url'], '/');
        $this->tokenCache         = $tokenCache;
        $this->authExceptionClass = NinjaOneAuthException::class;

        // NinjaOne API ne nécessite pas Content-Type sur les GET
        $this->defaultHeaders = ['Accept: application/json'];
    }

    // ── Organisations ───────────────────────────────────────────────

    /**
     * Récupère toutes les organisations via pagination curseur.
     *
     * @return array[]
     */
    public function getAllOrganizations(): array
    {
        $all   = [];
        $after = null;

        $this->log("Début récupération des organisations...");

        do {
            $params = ['pageSize' => self::PAGE_SIZE];
            if ($after !== null) {
                $params['after'] = $after;
            }

            $this->log("Requête organisations : after=" . ($after ?? 'début') . " size=" . self::PAGE_SIZE);

            $items = $this->getAuthenticated('/v2/organizations', $params);

            if (empty($items)) {
                break;
            }

            $all   = array_merge($all, $items);
            $last  = end($items);
            $after = $last['id'] ?? null;

            $this->log("Reçu " . count($items) . " organisations (cumulé: " . count($all) . ")");

            if (count($items) === self::PAGE_SIZE) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while (count($items) === self::PAGE_SIZE);

        $this->log("Total organisations récupérées : " . count($all));
        return $all;
    }

    // ── Équipements ─────────────────────────────────────────────────

    /**
     * Récupère tous les équipements via pagination curseur.
     * Chaque équipement contient : id, organizationId, nodeClass, etc.
     *
     * @return array[]
     */
    public function getAllDevices(): array
    {
        $all   = [];
        $after = null;

        $this->log("Début récupération des équipements...");

        do {
            $params = ['pageSize' => self::PAGE_SIZE];
            if ($after !== null) {
                $params['after'] = $after;
            }

            $this->log("Requête équipements : after=" . ($after ?? 'début') . " size=" . self::PAGE_SIZE);

            $items = $this->getAuthenticated('/v2/devices', $params);

            if (empty($items)) {
                break;
            }

            $all   = array_merge($all, $items);
            $last  = end($items);
            $after = $last['id'] ?? null;

            $this->log("Reçu " . count($items) . " équipements (cumulé: " . count($all) . ")");

            if (count($items) === self::PAGE_SIZE) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while (count($items) === self::PAGE_SIZE);

        $this->log("Total équipements récupérés : " . count($all));
        return $all;
    }

    // ── Interne ─────────────────────────────────────────────────────

    /**
     * GET authentifié avec retry automatique en cas d'expiration de token.
     */
    private function getAuthenticated(string $endpoint, array $query = []): array
    {
        $token = $this->tokenCache->getValidToken();

        try {
            return $this->get($endpoint, $query, [
                'Authorization: Bearer ' . $token,
            ]);
        } catch (NinjaOneAuthException) {
            $this->log("Token invalide (401), invalidation cache et retry...");
            $this->tokenCache->invalidate();
            $token = $this->tokenCache->getValidToken();

            return $this->get($endpoint, $query, [
                'Authorization: Bearer ' . $token,
            ]);
        }
    }

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [NinjaOneApi] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}
