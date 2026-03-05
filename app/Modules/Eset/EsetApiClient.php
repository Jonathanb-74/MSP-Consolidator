<?php

namespace App\Modules\Eset;

use App\Core\ApiClient;
use App\Modules\Eset\Exception\EsetAuthException;
use RuntimeException;

/**
 * Client API pour ESET MSP Administrator 2 (EMA2).
 *
 * Endpoints utilisés :
 *   POST /Token/Get          — obtention du token (délégué à EsetTokenCache)
 *   POST /Search/Companies   — liste paginée des companies
 *   POST /Search/Licenses    — licences par company
 *   POST /License/Detail     — détail d'une licence
 *
 * Rate limit : 10 req/s → usleep(110_000) entre chaque appel paginé.
 */
class EsetApiClient extends ApiClient
{
    private EsetTokenCache $tokenCache;

    // Nombre d'éléments par page pour la pagination
    private const PAGE_SIZE = 100;

    // Délai entre appels paginés pour respecter le rate limit (110ms > 100ms = 10 req/s)
    private const RATE_LIMIT_SLEEP_US = 110_000;

    /**
     * @param array          $credentials  Connexion depuis ProviderConfig::findConnection('eset', $key)
     * @param EsetTokenCache $tokenCache   Instance déjà créée avec les mêmes credentials
     */
    public function __construct(array $credentials, EsetTokenCache $tokenCache)
    {
        $this->baseUrl            = rtrim($credentials['base_url'], '/');
        $this->tokenCache         = $tokenCache;
        $this->authExceptionClass = EsetAuthException::class;
    }

    // ── Companies ──────────────────────────────────────────────────

    /**
     * Récupère toutes les companies via pagination automatique.
     *
     * @return array[] Tableau de companies
     */
    public function getAllCompanies(): array
    {
        $all  = [];
        $skip = 0;

        $this->log("Début récupération des companies...");

        do {
            $this->log("Requête companies : skip={$skip} take=" . self::PAGE_SIZE);

            $response = $this->postAuthenticated('/Search/Companies', [
                'search' => new \stdClass(),
                'skip'   => $skip,
                'take'   => self::PAGE_SIZE,
            ]);

            $items = $response['Search'] ?? [];
            $total = $response['Paging']['TotalCount'] ?? count($items);
            $all   = array_merge($all, $items);

            $this->log("Reçu " . count($items) . " companies (total déclaré: {$total}, cumulé: " . count($all) . ")");

            $skip += self::PAGE_SIZE;

            if ($skip < $total) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while ($skip < $total);

        $this->log("Total companies récupérées : " . count($all));
        return $all;
    }

    /**
     * Récupère le détail d'une company par son ID.
     */
    public function getCompanyDetail(string $companyId): array
    {
        return $this->postAuthenticated('/Company/Detail', ['companyId' => $companyId]);
    }

    // ── Licences ───────────────────────────────────────────────────

    /**
     * Récupère toutes les licences d'une company via pagination automatique.
     *
     * @return array[] Tableau de licences (résumé)
     */
    public function getLicensesByCompany(string $companyId): array
    {
        $all  = [];
        $skip = 0;

        do {
            $response = $this->postAuthenticated('/Search/Licenses', [
                'customerId' => $companyId,
                'skip'       => $skip,
                'take'       => self::PAGE_SIZE,
            ]);

            $items = $response['Search'] ?? [];
            $all   = array_merge($all, $items);
            $total = $response['TotalCount'] ?? count($items);

            $skip += self::PAGE_SIZE;

            if ($skip < $total) {
                usleep(self::RATE_LIMIT_SLEEP_US);
            }
        } while ($skip < $total);

        return $all;
    }

    /**
     * Récupère le détail complet d'une licence.
     */
    public function getLicenseDetail(string $publicLicenseKey): array
    {
        return $this->postAuthenticated('/License/Detail', [
            'publicLicenseKey' => $publicLicenseKey,
        ]);
    }

    // ── Interne ────────────────────────────────────────────────────

    /**
     * POST authentifié avec retry automatique en cas d'expiration de token.
     */
    private function postAuthenticated(string $endpoint, array $body): array
    {
        $bodyPreview = substr(json_encode($body), 0, 200);
        $this->log("→ POST {$endpoint} | Payload: {$bodyPreview}");

        $token = $this->tokenCache->getValidToken();

        try {
            $response = $this->post($endpoint, $body, [
                'Authorization: Bearer ' . $token,
            ]);

            $keyCount = is_array($response) ? count($response) : 0;
            $this->log("← Succès {$endpoint} | {$keyCount} clés dans la réponse.");

            return $response;
        } catch (EsetAuthException) {
            // Token invalide → invalider le cache et réessayer une fois
            $this->log("Token invalide (401), invalidation cache et retry...");
            $this->tokenCache->invalidate();
            $token = $this->tokenCache->getValidToken();

            return $this->post($endpoint, $body, [
                'Authorization: Bearer ' . $token,
            ]);
        }
    }

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [EsetApi] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}
