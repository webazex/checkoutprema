<?php

declare(strict_types=1);

namespace common\integrations\novaposhta;

use RuntimeException;
use Throwable;
use yii\helpers\Json;

final class NovaPoshtaApiClient
{
    private const MODEL_ADDRESS = 'Address';

    private const METHOD_SEARCH_SETTLEMENTS = 'searchSettlements';
    private const METHOD_GET_WAREHOUSES = 'getWarehouses';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 15,
    ) {
    }

    /**
     * Ищет населённые пункты по введённой строке.
     *
     * Возвращает массив из response.data без дополнительной нормализации.
     */
    public function searchSettlements(
        string $query,
        int $limit = 20,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $response = $this->call(
            modelName: self::MODEL_ADDRESS,
            calledMethod: self::METHOD_SEARCH_SETTLEMENTS,
            methodProperties: [
                'CityName' => $query,
                'Limit' => max(1, min($limit, 100)),
            ],
        );

        return $this->extractData($response);
    }

    /**
     * Получает список отделений для выбранного населённого пункта.
     *
     * Поиск по номеру и адресу пока намеренно не передаём в API:
     * полный список города будет кешироваться и фильтроваться нашим сервисом.
     */
    public function getWarehouses(
        string $cityRef,
        int $page = 1,
        int $limit = 500,
    ): array {
        $cityRef = trim($cityRef);

        if ($cityRef === '') {
            throw new RuntimeException('Nova Poshta city reference must not be empty.');
        }

        $response = $this->call(
            modelName: self::MODEL_ADDRESS,
            calledMethod: self::METHOD_GET_WAREHOUSES,
            methodProperties: [
                'CityRef' => $cityRef,
                'Page' => max(1, $page),
                'Limit' => max(1, min($limit, 500)),
            ],
        );

        return $this->extractData($response);
    }

    /**
     * Выполняет универсальный запрос к Nova Poshta API 2.0.
     */
    public function call(
        string $modelName,
        string $calledMethod,
        array $methodProperties = [],
    ): array {
        $modelName = trim($modelName);
        $calledMethod = trim($calledMethod);

        if ($modelName === '') {
            throw new RuntimeException(
                'Nova Poshta model name must not be empty.'
            );
        }

        if ($calledMethod === '') {
            throw new RuntimeException(
                'Nova Poshta method name must not be empty.'
            );
        }

        $payload = [
            'apiKey' => $this->apiKey,
            'modelName' => $modelName,
            'calledMethod' => $calledMethod,
            'methodProperties' => $methodProperties === []
                ? new \stdClass()
                : $methodProperties,
        ];

        return $this->request($payload);
    }

    private function request(array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . '/';

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException(
                'Failed to initialize cURL for Nova Poshta request.'
            );
        }

        try {
            $requestBody = Json::encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            if ($responseBody === false) {
                throw new RuntimeException(
                    'Nova Poshta request failed: ' . $curlError
                );
            }

            try {
                $decoded = Json::decode($responseBody, true);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Nova Poshta returned an invalid JSON response.',
                    previous: $e,
                );
            }

            if (!is_array($decoded)) {
                throw new RuntimeException(
                    'Nova Poshta returned a non-object JSON response.'
                );
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException(sprintf(
                    'Nova Poshta request failed with HTTP %d: %s',
                    $httpCode,
                    $this->formatApiMessages($decoded)
                ));
            }

            if (($decoded['success'] ?? false) !== true) {
                throw new RuntimeException(
                    'Nova Poshta API rejected the request: '
                    . $this->formatApiMessages($decoded)
                );
            }

            return $decoded;
        } finally {
            unset($ch);
        }
    }

    private function extractData(array $response): array
    {
        $data = $response['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    private function formatApiMessages(array $response): string
    {
        $parts = [];

        foreach ([
                     'errors',
                     'warnings',
                     'errorCodes',
                     'warningCodes',
                     'messageCodes',
                 ] as $key) {
            $values = $response[$key] ?? null;

            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (is_scalar($value)) {
                    $message = trim((string)$value);

                    if ($message !== '') {
                        $parts[] = $message;
                    }
                }
            }
        }

        if ($parts !== []) {
            return implode('; ', array_unique($parts));
        }

        return Json::encode(
            $response,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}