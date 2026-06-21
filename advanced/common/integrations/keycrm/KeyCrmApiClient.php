<?php

declare(strict_types=1);

namespace common\integrations\keycrm;

use common\services\keycrm\KeyCrmRateLimiter;
use RuntimeException;
use yii\helpers\Json;

final class KeyCrmApiClient
{
    public function __construct(
        private readonly string             $baseUrl,
        private readonly string             $token,
        private readonly int                $timeout = 30,
        private readonly ?KeyCrmRateLimiter $rateLimiter = null,
    )
    {
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request(
            method: 'GET',
            path: $path,
            query: $query,
        );
    }

    private function request(
        string $method,
        string $path,
        array  $query = [],
        array  $body = [],
    ): array
    {
        $this->rateLimiter?->beforeRequest();

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL for KeyCRM request.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (strtoupper($method) !== 'GET') {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = Json::encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($responseBody === false) {
            throw new RuntimeException('KeyCRM request failed: ' . $curlError);
        }

        $decoded = Json::decode($responseBody, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('KeyCRM returned non-JSON response.');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf(
                'KeyCRM request failed with HTTP %d: %s',
                $httpCode,
                Json::encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ));
        }

        return $decoded;
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        return $this->request(
            method: 'POST',
            path: $path,
            query: $query,
            body: $body,
        );
    }
}