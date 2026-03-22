<?php

namespace App\Modules\BeCloud;

use App\Modules\BeCloud\Exception\BeCloudAuthException;
use RuntimeException;

/**
 * Gestion du token Be-Cloud (CloudCockpit / Microsoft OAuth2).
 *
 * Auth endpoint  : POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
 * Grant type     : client_credentials
 * Scope          : api://b92a36a4-feb8-4f47-a69c-29a180aa6d0a/.default
 * Clé réponse   : access_token
 * Token validity : expires_in (~3600s)
 * Stocké dans    : storage/cache/becloud_token_{key}.json
 */
class BeCloudTokenCache
{
    private string $cacheFile;
    private string $authUrl;
    private string $clientId;
    private string $clientSecret;
    private string $scope;
    private bool   $sslVerify;

    // Renouveler 60s avant expiration
    private const SAFETY_MARGIN_SECONDS = 60;

    // Valeurs publiques Be-Cloud (doc officielle) utilisées comme fallback
    private const DEFAULT_TENANT_ID = '4e806121-ff28-4286-ab4e-3be0a08f9ce0';
    private const DEFAULT_SCOPE     = 'api://b92a36a4-feb8-4f47-a69c-29a180aa6d0a/.default';

    /**
     * @param array $credentials  Connexion depuis ProviderConfig::findConnection('becloud', $key)
     *                            Doit contenir : client_id, client_secret, key (optionnel)
     *                            Peut contenir : tenant_id, scope (sinon valeurs publiques Be-Cloud)
     */
    public function __construct(array $credentials)
    {
        $appConfig = require dirname(__DIR__, 3) . '/config/app.php';

        $tenantId        = $credentials['tenant_id'] ?? self::DEFAULT_TENANT_ID;
        $cacheKey        = $credentials['key'] ?? md5($credentials['client_id'] ?? '');
        $this->cacheFile = $appConfig['cache_path'] . '/becloud_token_' . preg_replace('/[^a-z0-9_]/', '_', $cacheKey) . '.json';
        $this->authUrl   = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $this->clientId  = $credentials['client_id'];
        $this->clientSecret = $credentials['client_secret'];
        $this->scope     = $credentials['scope'] ?? self::DEFAULT_SCOPE;
        $this->sslVerify = (bool)$appConfig['ssl_verify'];
    }

    /**
     * Retourne un access_token valide, en le renouvelant si nécessaire.
     */
    public function getValidToken(): string
    {
        $cached = $this->loadCache();

        if ($cached !== null && $this->isTokenValid($cached)) {
            $remaining = $cached['expires_at'] - time();
            $this->log("Token en cache valide (expire dans {$remaining}s).");
            return $cached['access_token'];
        }

        $this->log("Token absent ou expiré → ré-authentification...");
        return $this->authenticate();
    }

    /**
     * Authentification via OAuth2 client_credentials (form-urlencoded).
     */
    private function authenticate(): string
    {
        $this->log("POST {$this->authUrl}");

        $postFields = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $this->scope,
            'grant_type'    => 'client_credentials',
        ]);

        $ch = curl_init($this->authUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $this->log("HTTP {$httpCode} — Réponse brute : " . substr((string)$response, 0, 400));

        if ($error) {
            throw new RuntimeException("cURL error (token Be-Cloud) : $error");
        }

        if ($httpCode === 400 || $httpCode === 401) {
            $body   = json_decode((string)$response, true);
            $detail = $body['error_description'] ?? $body['error'] ?? substr((string)$response, 0, 400);
            throw new BeCloudAuthException("Credentials Be-Cloud invalides (HTTP {$httpCode}) : {$detail}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Erreur token Be-Cloud HTTP {$httpCode} : " . substr((string)$response, 0, 400));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Réponse token JSON invalide : " . substr((string)$response, 0, 400));
        }

        $accessToken = $decoded['access_token'] ?? null;
        if (empty($accessToken)) {
            $keys = implode(', ', array_keys($decoded));
            throw new BeCloudAuthException(
                "access_token absent dans la réponse. Clés disponibles : {$keys}."
            );
        }

        $expiresIn = (int)($decoded['expires_in'] ?? 3600);
        $this->log("Token Be-Cloud obtenu avec succès. Expire dans {$expiresIn}s.");

        $this->saveCache($accessToken, $expiresIn);
        return $accessToken;
    }

    private function isTokenValid(array $cached): bool
    {
        return isset($cached['expires_at'])
            && ($cached['expires_at'] - time()) > self::SAFETY_MARGIN_SECONDS;
    }

    private function loadCache(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        $data = json_decode(file_get_contents($this->cacheFile), true);
        return is_array($data) ? $data : null;
    }

    private function saveCache(string $accessToken, int $expiresIn): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $data = [
            'access_token' => $accessToken,
            'obtained_at'  => time(),
            'expires_at'   => time() + $expiresIn,
        ];

        file_put_contents($this->cacheFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Invalide le cache (utile en cas d'erreur 401 inattendue).
     */
    public function invalidate(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
            $this->log("Cache token Be-Cloud invalidé.");
        }
    }

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [BeCloudToken] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}
