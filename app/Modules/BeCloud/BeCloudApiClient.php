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

    private const PAGE_SIZE = 250;

    // Délai prudentiel entre pages (rate limit non documenté)
    private const RATE_LIMIT_SLEEP_US = 50_000;

    public function __construct(array $credentials, BeCloudTokenCache $tokenCache)
    {
        $this->baseUrl            = rtrim($credentials['base_url'], '/');
        $this->tokenCache         = $tokenCache;
        $this->authExceptionClass = BeCloudAuthException::class;

        // CloudCockpit requiert X-Tenant sur toutes les requêtes
        $this->defaultHeaders = [
            'Accept: application/json',
            'X-Tenant: portal.cloudcockpit.com',
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

    // ── Interne ────────────────────────────────────────────────────

    /**
     * GET authentifié avec retry automatique en cas d'expiration de token.
     * X-Correlation-Id (UUID v4) requis par CloudCockpit malgré la doc "optional".
     */
    private function getAuthenticated(string $endpoint, array $query = []): array
    {
        $token         = $this->tokenCache->getValidToken();
        $correlationId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

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
