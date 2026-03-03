<?php

namespace App\Core;

use RuntimeException;

/**
 * Client HTTP de base utilisant cURL.
 * Chaque module fournisseur étend cette classe.
 */
abstract class ApiClient
{
    protected string $baseUrl = '';
    protected int $timeoutSeconds = 30;
    protected array $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    protected function post(string $endpoint, array $body = [], array $headers = []): array
    {
        return $this->request('POST', $endpoint, $body, $headers);
    }

    protected function get(string $endpoint, array $query = [], array $headers = []): array
    {
        $url = $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url, [], $headers);
    }

    protected function request(string $method, string $endpoint, array $body = [], array $extraHeaders = []): array
    {
        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->baseUrl . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);

        $headers = array_merge($this->defaultHeaders, $extraHeaders);

        $sslVerify = (require dirname(__DIR__, 2) . '/config/app.php')['ssl_verify'];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("cURL error : $error");
        }

        if ($httpCode === 429) {
            throw new RuntimeException("Rate limit atteint (HTTP 429). Réessayer dans quelques secondes.");
        }

        if ($httpCode === 401) {
            throw new \App\Modules\Eset\Exception\EsetAuthException("Token invalide ou expiré (HTTP 401).");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("API error HTTP $httpCode : " . substr((string)$response, 0, 500));
        }

        $decoded = json_decode((string)$response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Réponse JSON invalide : " . json_last_error_msg());
        }

        return $decoded ?? [];
    }
}
