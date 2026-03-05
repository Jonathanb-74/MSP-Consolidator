<?php

namespace App\Modules\Eset;

use App\Modules\Eset\Exception\EsetAuthException;
use RuntimeException;

/**
 * Gestion du token ESET EMA2 (mspapi.eset.com).
 *
 * Auth endpoint  : POST /api/Token/Get  (application/json)
 * Body           : {"username": "...", "password": "..."}
 * Clé réponse   : accessToken (camelCase)
 * Token validity : expiresIn secondes (ou 3000s par défaut)
 * Stocké dans    : storage/cache/eset_token.json
 */
class EsetTokenCache
{
    private string $cacheFile;
    private string $authUrl;
    private string $username;
    private string $password;
    private bool   $sslVerify;

    // Renouveler 60s avant expiration
    private const SAFETY_MARGIN_SECONDS = 60;

    /**
     * @param array $credentials  Connexion depuis ProviderConfig::findConnection('eset', $key)
     *                            Doit contenir : username, password, base_url, key (optionnel)
     */
    public function __construct(array $credentials)
    {
        $appConfig = require dirname(__DIR__, 3) . '/config/app.php';

        // Clé de cache unique par connexion (évite les collisions entre plusieurs consoles ESET)
        $cacheKey        = $credentials['key'] ?? md5($credentials['username'] ?? '');
        $this->cacheFile = $appConfig['cache_path'] . '/eset_token_' . preg_replace('/[^a-z0-9_]/', '_', $cacheKey) . '.json';
        $this->authUrl   = rtrim($credentials['base_url'], '/') . '/Token/Get';
        $this->username  = $credentials['username'];
        $this->password  = $credentials['password'];
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
     * Authentification via POST /Token/Get (JSON).
     */
    private function authenticate(): string
    {
        $this->log("POST {$this->authUrl}");
        $this->log("Body: {\"username\":\"{$this->username}\",\"password\":\"***\"}");

        $ch = curl_init($this->authUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'username' => $this->username,
                'password' => $this->password,
            ]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
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
            throw new RuntimeException("cURL error (token) : $error");
        }

        if ($httpCode === 400 || $httpCode === 401) {
            $body   = json_decode((string)$response, true);
            $detail = $body['error_description']
                ?? $body['error']
                ?? $body['message']
                ?? $body['Message']
                ?? substr((string)$response, 0, 400);
            throw new EsetAuthException("Credentials ESET invalides (HTTP {$httpCode}) : {$detail}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Erreur token HTTP {$httpCode} : " . substr((string)$response, 0, 400));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Réponse token JSON invalide : " . substr((string)$response, 0, 400));
        }

        // La clé peut être 'accessToken' ou 'AccessToken' selon la version EMA2
        $accessToken = $decoded['accessToken'] ?? $decoded['AccessToken'] ?? null;
        if (empty($accessToken)) {
            $keys = implode(', ', array_keys($decoded));
            throw new EsetAuthException(
                "accessToken absent dans la réponse. Clés disponibles : {$keys}. " .
                "Réponse : " . substr((string)$response, 0, 400)
            );
        }

        $expiresIn = (int)($decoded['expiresIn'] ?? $decoded['ExpiresIn'] ?? 3000);
        $this->log("Token obtenu avec succès. Expire dans {$expiresIn}s.");

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
            $this->log("Cache token invalidé.");
        }
    }

    private function log(string $message): void
    {
        $ts  = date('Y-m-d H:i:s');
        $msg = "[{$ts}] [EsetToken] {$message}";
        error_log($msg);
        if (PHP_SAPI === 'cli') {
            echo $msg . PHP_EOL;
        }
    }
}
