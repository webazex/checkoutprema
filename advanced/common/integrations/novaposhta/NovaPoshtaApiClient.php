<?php

declare(strict_types=1);

namespace common\integrations\novaposhta;

use RuntimeException;
use stdClass;
use Throwable;
use yii\helpers\Json;

final class NovaPoshtaApiClient
{
    private readonly string $baseUrl;
    private readonly string $apiKey;
    private readonly int $timeout;

    public function __construct(string $baseUrl, string $apiKey, int $timeout = 15)
    {
        $baseUrl = trim($baseUrl);
        $apiKey = trim($apiKey);

        if ($baseUrl === '') {
            throw new RuntimeException('Nova Poshta API base URL must not be empty.');
        }

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Nova Poshta API base URL is invalid.');
        }

        if ($apiKey === '') {
            throw new RuntimeException('Nova Poshta API key must not be empty.');
        }

        if ($timeout < 1) {
            throw new RuntimeException('Nova Poshta API timeout must be greater than zero.');
        }

        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Выполняет универсальный запрос к Nova Poshta API 2.0
     * и возвращает полный сырой ответ API.
     *
     * @param array<string, mixed> $methodProperties
     *
     * @return array<string, mixed>
     */
    public function call(string $modelName, string $calledMethod, array $methodProperties = []): array
    {
        $modelName = trim($modelName);
        $calledMethod = trim($calledMethod);

        if ($modelName === '') {
            throw new RuntimeException('Nova Poshta model name must not be empty.');
        }

        if ($calledMethod === '') {
            throw new RuntimeException('Nova Poshta method name must not be empty.');
        }

        $payload = [
            'apiKey' => $this->apiKey,
            'modelName' => $modelName,
            'calledMethod' => $calledMethod,
            'methodProperties' => $methodProperties === [] ? new stdClass() : $methodProperties,
        ];

        return $this->request($payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        $ch = curl_init($this->baseUrl);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL for Nova Poshta request.');
        }

        try {
            $requestBody = $this->encodePayload($payload);

            $configured = curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
                CURLOPT_ENCODING => '',
            ]);

            if ($configured === false) {
                throw new RuntimeException('Failed to configure cURL for Nova Poshta request.');
            }

            $responseBody = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            if ($responseBody === false) {
                $curlError = trim(curl_error($ch));

                throw new RuntimeException(
                    'Nova Poshta request failed: ' . ($curlError !== '' ? $curlError : 'unknown cURL error')
                );
            }

            $response = $this->decodeResponse($responseBody);

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException(sprintf(
                    'Nova Poshta request failed with HTTP %d: %s',
                    $httpCode,
                    $this->formatApiMessages($response)
                ));
            }

            if (($response['success'] ?? false) !== true) {
                throw new RuntimeException(
                    'Nova Poshta API rejected the request: ' . $this->formatApiMessages($response)
                );
            }

            return $response;
        } finally {
            unset($ch);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        try {
            return Json::encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to encode Nova Poshta request payload.', previous: $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string $responseBody): array
    {
        try {
            $response = Json::decode($responseBody, true);
        } catch (Throwable $e) {
            throw new RuntimeException('Nova Poshta returned an invalid JSON response.', previous: $e);
        }

        if (!is_array($response)) {
            throw new RuntimeException('Nova Poshta returned a non-object JSON response.');
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function formatApiMessages(array $response): string
    {
        $messages = [];

        foreach (['errors', 'warnings', 'errorCodes', 'warningCodes', 'messageCodes'] as $key) {
            $values = $response[$key] ?? null;

            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                $message = trim((string)$value);

                if ($message !== '') {
                    $messages[] = $message;
                }
            }
        }

        if ($messages !== []) {
            return implode('; ', array_unique($messages));
        }

        return Json::encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}