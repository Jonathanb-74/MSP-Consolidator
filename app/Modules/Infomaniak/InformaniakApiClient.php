<?php

namespace App\Modules\Infomaniak;

use App\Core\ApiClient;
use RuntimeException;

/**
 * Client API pour Infomaniak.
 *
 * Base URL : https://api.infomaniak.com
 * Endpoints utilisés :
 *   GET /1/products  — liste de tous les produits du compte revendeur
 *   GET /1/accounts/{id} — détails d'un compte client
 *
 * Auth : Bearer token statique (pas d'OAuth)
 */
class InformaniakApiClient extends ApiClient
{
    // Délai prudentiel entre requêtes (ms)
    private const RATE_LIMIT_SLEEP_US = 100_000;

    public function __construct(string $apiToken, string $baseUrl = 'https://api.infomaniak.com')
    {
        $this->baseUrl        = rtrim($baseUrl, '/');
        $this->defaultHeaders = [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiToken,
        ];
    }

    /**
     * Récupère tous les produits via pagination automatique.
     *
     * @return array[]
     */
    public function getProducts(): array
    {
        $all  = [];
        $page = 1;

        $this->log("Début récupération des produits...");

        do {
            $this->log("Requête produits : page={$page}");

            $response = $this->get('/1/products', ['page' => $page, 'per_page' => 100]);

            $data = $response['data'] ?? [];

            // data peut être un tableau direct (liste indexée) ou pagné (avec clé items/list)
            if (is_array($data) && !empty($data) && isset($data[0])) {
                $items = $data;
            } elseif (is_array($data) && $data === array_values($data) && !isset($data['items'])) {
                $items = $data;
            } else {
                $items = $data['items'] ?? $data['list'] ?? [];
            }

            $all   = array_merge($all, $items);
            $total = (int)($response['total'] ?? $response['data']['total'] ?? 0);

            $this->log("Reçu " . count($items) . " produits (cumulé: " . count($all) . ($total ? ", total: {$total}" : '') . ")");

            $hasMore = count($items) === 100 && ($total === 0 || count($all) < $total);
            $page++;

            if ($hasMore) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while ($hasMore);

        $this->log("Total produits récupérés : " . count($all));
        return $all;
    }

    /**
     * Récupère les informations d'un compte par son ID.
     */
    public function getAccount(int $accountId): ?array
    {
        try {
            $response = $this->get("/1/accounts/{$accountId}");
            return $response['data'] ?? null;
        } catch (RuntimeException $e) {
            $this->log("Erreur récupération compte #{$accountId} : " . $e->getMessage());
            return null;
        }
    }

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [InformaniakApi] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}
