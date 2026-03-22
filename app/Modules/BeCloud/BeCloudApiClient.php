<?php

namespace App\Modules\BeCloud;

use App\Core\ApiClient;
use App\Modules\BeCloud\Exception\BeCloudAuthException;
use RuntimeException;

/**
 * Client API pour Be-Cloud (CloudCockpit Reseller API).
 *
 * Base URL : https://api.cloudcockpit.com
 * Endpoints utilisés :
 *   GET /v1/customers              — liste paginée des customers
 *   GET /v1/customers/{id}/subscriptions — abonnements d'un customer
 *
 * Pagination : pageNumber (1-based), pageSize (max 2000), hasNextPage
 * Auth : Bearer token (OAuth2 client_credentials via Microsoft)
 */
class BeCloudApiClient extends ApiClient
{
    private BeCloudTokenCache $tokenCache;
    private string $correlationIdPrefix;

    private const PAGE_SIZE = 250;

    // Délai prudentiel entre pages (rate limit non documenté)
    private const RATE_LIMIT_SLEEP_US = 50_000;

    public function __construct(array $credentials, BeCloudTokenCache $tokenCache)
    {
        $this->baseUrl              = rtrim($credentials['base_url'], '/');
        $this->tokenCache           = $tokenCache;
        $this->authExceptionClass   = BeCloudAuthException::class;
        $this->correlationIdPrefix  = $credentials['correlation_id_prefix'] ?? '';

        // CloudCockpit requiert X-Tenant sur toutes les requêtes
        // La valeur (csp_url) est propre à chaque revendeur — ex. "csp.lti.eu"
        $cspUrl = $credentials['csp_url'] ?? 'csp.lti.eu';
        $this->defaultHeaders = [
            'Accept: application/json',
            'X-Tenant: ' . $cspUrl,
        ];
    }

    // ── Customers ──────────────────────────────────────────────────

    /**
     * Récupère tous les customers via pagination automatique.
     *
     * @return array[]
     */
    public function getAllCustomers(): array
    {
        $all        = [];
        $pageNumber = 1;

        $this->log("Début récupération des customers...");

        do {
            $this->log("Requête customers : page={$pageNumber} size=" . self::PAGE_SIZE);

            $response = $this->getAuthenticated('/v1/customers', [
                'pageNumber' => $pageNumber,
                'pageSize'   => self::PAGE_SIZE,
            ]);

            $items       = $response['items'] ?? [];
            $hasNextPage = (bool)($response['hasNextPage'] ?? false);
            $totalCount  = $response['totalCount'] ?? '?';
            $all         = array_merge($all, $items);

            $this->log("Reçu " . count($items) . " customers (total: {$totalCount}, cumulé: " . count($all) . ")");

            $pageNumber++;

            if ($hasNextPage) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while ($hasNextPage);

        $this->log("Total customers récupérés : " . count($all));
        return $all;
    }

    // ── Subscriptions ──────────────────────────────────────────────

    /**
     * Récupère tous les abonnements d'un customer via pagination automatique.
     *
     * @return array[]
     */
    public function getSubscriptionsByCustomer(string $customerId): array
    {
        $all        = [];
        $pageNumber = 1;

        do {
            $response = $this->getAuthenticated("/v1/customers/{$customerId}/subscriptions", [
                'pageNumber' => $pageNumber,
                'pageSize'   => self::PAGE_SIZE,
            ]);

            $items       = $response['items'] ?? [];
            $hasNextPage = (bool)($response['hasNextPage'] ?? false);
            $all         = array_merge($all, $items);

            $pageNumber++;

            if ($hasNextPage) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while ($hasNextPage);

        return $all;
    }

    // ── Licenses ───────────────────────────────────────────────────

    /**
     * Récupère les licences M365/cloud d'un customer.
     * Nécessite un providerInstanceId issu de ses subscriptions.
     *
     * @return array[]
     */
    public function getLicensesByCustomer(string $customerId, string $providerInstanceId): array
    {
        $response = $this->getAuthenticated(
            "/v1/Customers/{$customerId}/licenses",
            ['providerInstanceId' => $providerInstanceId]
        );

        // L'API retourne soit un tableau direct, soit { items: [...] }
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }
        return is_array($response) ? $response : [];
    }

    // ── Interne ────────────────────────────────────────────────────

    /**
     * GET authentifié avec retry automatique en cas d'expiration de token.
     * X-Correlation-Id (UUID v4) requis par CloudCockpit malgré la doc "optional".
     * Un préfixe configurable peut être ajouté pour faciliter le dépannage avec le support.
     */
    private function getAuthenticated(string $endpoint, array $query = []): array
    {
        $token = $this->tokenCache->getValidToken();
        $uuid  = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $correlationId = $this->correlationIdPrefix . $uuid;

        $extraHeaders = [
            'Authorization: Bearer ' . $token,
            'X-Correlation-Id: ' . $correlationId,
        ];

        try {
            return $this->get($endpoint, $query, $extraHeaders);
        } catch (BeCloudAuthException) {
            // Token invalide → invalider le cache et réessayer une fois
            $this->log("Token invalide (401), invalidation cache et retry...");
            $this->tokenCache->invalidate();
            $token = $this->tokenCache->getValidToken();

            $extraHeaders[0] = 'Authorization: Bearer ' . $token;
            return $this->get($endpoint, $query, $extraHeaders);
        }
    }

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [BeCloudApi] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}
