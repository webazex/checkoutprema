<?php

declare(strict_types=1);

namespace common\integrations\novaposhta;

use common\integrations\novaposhta\exceptions\NovaPoshtaApiException;
use common\integrations\novaposhta\exceptions\NovaPoshtaRateLimiterException;
use common\services\novaposhta\NovaPoshtaLogFormatter;
use common\services\novaposhta\NovaPoshtaRateLimiter;
use InvalidArgumentException;
use LogicException;
use stdClass;
use Throwable;
use Yii;
use yii\helpers\Json;

final class NovaPoshtaApiClient
{
    /**
     * Код превышения частоты запросов Nova Poshta API.
     */
    private const API_RATE_LIMIT_CODE = '20000401501';

    private const LOG_CATEGORY_REQUEST = 'novaposhta.api.request';
    private const LOG_CATEGORY_RETRY = 'novaposhta.api.retry';
    private const LOG_CATEGORY_ERROR = 'novaposhta.api.error';

    /**
     * CURLE_OPERATION_TIMEDOUT.
     *
     * @var list<int>
     */
    private const CURL_TIMEOUT_ERROR_CODES = [
        28,
    ];

    /**
     * Временные сетевые ошибки cURL:
     *
     * 5  — CURLE_COULDNT_RESOLVE_PROXY
     * 6  — CURLE_COULDNT_RESOLVE_HOST
     * 7  — CURLE_COULDNT_CONNECT
     * 18 — CURLE_PARTIAL_FILE
     * 35 — CURLE_SSL_CONNECT_ERROR
     * 52 — CURLE_GOT_NOTHING
     * 55 — CURLE_SEND_ERROR
     * 56 — CURLE_RECV_ERROR
     * 92 — CURLE_HTTP2_STREAM
     *
     * @var list<int>
     */
    private const CURL_CONNECTION_ERROR_CODES = [
        5,
        6,
        7,
        18,
        35,
        52,
        55,
        56,
        92,
    ];

    private readonly string $baseUrl;
    private readonly string $apiKey;
    private readonly int $timeout;
    private readonly int $maxAttempts;
    private readonly int $retryBaseDelayMs;
    private readonly int $retryMaxDelayMs;
    private readonly int $retryJitterMs;

    public function __construct(string $baseUrl, string $apiKey, int $timeout = 15, private readonly ?NovaPoshtaRateLimiter $rateLimiter = null, int $maxAttempts = 3, int $retryBaseDelayMs = 500, int $retryMaxDelayMs = 5000, int $retryJitterMs = 250)
    {
        $baseUrl = trim($baseUrl);
        $apiKey = trim($apiKey);

        if ($baseUrl === '') {
            throw new InvalidArgumentException(
                'Nova Poshta API base URL must not be empty.'
            );
        }

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(
                'Nova Poshta API base URL is invalid.'
            );
        }

        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'Nova Poshta API key must not be empty.'
            );
        }

        if ($timeout < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta API timeout must be greater than zero.'
            );
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta API maximum attempts must be greater than zero.'
            );
        }

        if ($retryBaseDelayMs < 0) {
            throw new InvalidArgumentException(
                'Nova Poshta API retry base delay must not be negative.'
            );
        }

        if ($retryMaxDelayMs < $retryBaseDelayMs) {
            throw new InvalidArgumentException(
                'Nova Poshta API retry maximum delay must be greater than or equal to the base delay.'
            );
        }

        if ($retryJitterMs < 0) {
            throw new InvalidArgumentException(
                'Nova Poshta API retry jitter must not be negative.'
            );
        }

        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->maxAttempts = $maxAttempts;
        $this->retryBaseDelayMs = $retryBaseDelayMs;
        $this->retryMaxDelayMs = $retryMaxDelayMs;
        $this->retryJitterMs = $retryJitterMs;
    }

    /**
     * Выполняет универсальный запрос к Nova Poshta API 2.0
     * и возвращает полный сырой ответ API.
     *
     * @param array<string, mixed> $methodProperties
     *
     * @return array<string, mixed>
     *
     * @throws NovaPoshtaApiException
     */
    public function call(string $modelName, string $calledMethod, array $methodProperties = []): array
    {
        $modelName = trim($modelName);
        $calledMethod = trim($calledMethod);

        if ($modelName === '') {
            throw new InvalidArgumentException(
                'Nova Poshta model name must not be empty.'
            );
        }

        if ($calledMethod === '') {
            throw new InvalidArgumentException(
                'Nova Poshta method name must not be empty.'
            );
        }

        $payload = [
            'apiKey' => $this->apiKey,
            'modelName' => $modelName,
            'calledMethod' => $calledMethod,
            'methodProperties' => $methodProperties === []
                ? new stdClass()
                : $methodProperties,
        ];

        return $this->request(
            payload: $payload,
            modelName: $modelName,
            calledMethod: $calledMethod,
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws NovaPoshtaApiException
     */
    private function request(array $payload, string $modelName, string $calledMethod): array
    {
        /*
         * Сериализация выполняется до получения rate-limit slot.
         *
         * Некорректный локальный payload не должен занимать разрешённый
         * интервал внешнего API-запроса.
         */
        $requestBody = $this->encodePayload(
            payload: $payload,
            modelName: $modelName,
            calledMethod: $calledMethod,
        );

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $this->beforeRequest(
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );

                $result = $this->performRequest(
                    requestBody: $requestBody,
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );

                Yii::info(
                    NovaPoshtaLogFormatter::event(
                        component: 'api',
                        status: 'SUCCESS',
                        context: [
                            'modelName' => $modelName,
                            'calledMethod' => $calledMethod,
                            'attempt' => $attempt,
                            'httpStatus' => $result['httpStatus'],
                            'durationMs' => $result['durationMs'],
                        ]
                    ),
                    self::LOG_CATEGORY_REQUEST
                );

                return $result['response'];
            } catch (NovaPoshtaApiException $exception) {
                if ($exception->attempt !== $attempt) {
                    $exception = $exception->withAttempt($attempt);
                }

                if (!$exception->retryable || $attempt >= $this->maxAttempts) {
                    Yii::error(
                        NovaPoshtaLogFormatter::event(
                            component: 'api',
                            status: 'FAILED',
                            context: NovaPoshtaLogFormatter::exceptionContext(
                                $exception
                            )
                        ),
                        self::LOG_CATEGORY_ERROR
                    );

                    throw $exception;
                }

                $delayMs = $this->calculateRetryDelayMs($attempt);

                Yii::warning(
                    NovaPoshtaLogFormatter::event(
                        component: 'api',
                        status: 'RETRY',
                        context: array_merge(
                            NovaPoshtaLogFormatter::exceptionContext(
                                $exception
                            ),
                            [
                                'nextAttempt' => $attempt + 1,
                                'delayMs' => $delayMs,
                            ]
                        )
                    ),
                    self::LOG_CATEGORY_RETRY
                );

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw new LogicException(
            'Nova Poshta API retry loop finished without a result or exception.'
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NovaPoshtaApiException
     */
    private function encodePayload(array $payload, string $modelName, string $calledMethod): string
    {
        try {
            return Json::encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (Throwable $exception) {
            throw new NovaPoshtaApiException(
                message: 'Failed to encode Nova Poshta request payload.',
                errorType: NovaPoshtaApiException::TYPE_TRANSPORT,
                retryable: false,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: 1,
                previous: $exception,
            );
        }
    }

    /**
     * @throws NovaPoshtaApiException
     */
    private function beforeRequest(string $modelName, string $calledMethod, int $attempt): void
    {
        if ($this->rateLimiter === null) {
            return;
        }

        try {
            $this->rateLimiter->beforeRequest();
        } catch (NovaPoshtaRateLimiterException $exception) {
            throw new NovaPoshtaApiException(
                message: $exception->getMessage(),
                errorType: NovaPoshtaApiException::TYPE_RATE_LIMITER,
                retryable: true,
                errors: [
                    $exception->getMessage(),
                ],
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
                previous: $exception,
            );
        }
    }

    /**
     * @return array{
     *     response: array<string, mixed>,
     *     httpStatus: int,
     *     durationMs: int
     * }
     *
     * @throws NovaPoshtaApiException
     */
    private function performRequest(string $requestBody, string $modelName, string $calledMethod, int $attempt): array
    {
        $ch = curl_init($this->baseUrl);

        if ($ch === false) {
            throw new NovaPoshtaApiException(
                message: 'Failed to initialize cURL for Nova Poshta request.',
                errorType: NovaPoshtaApiException::TYPE_TRANSPORT,
                retryable: false,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
            );
        }

        $startedAt = microtime(true);

        try {
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
                CURLOPT_NOSIGNAL => true,
            ]);

            if ($configured === false) {
                throw new NovaPoshtaApiException(
                    message: 'Failed to configure cURL for Nova Poshta request.',
                    errorType: NovaPoshtaApiException::TYPE_TRANSPORT,
                    retryable: false,
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );
            }

            $responseBody = curl_exec($ch);
            $httpStatus = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

            if ($responseBody === false) {
                $curlErrorCode = curl_errno($ch);
                $curlErrorMessage = trim(curl_error($ch));

                throw $this->createCurlException(
                    curlErrorCode: $curlErrorCode,
                    curlErrorMessage: $curlErrorMessage,
                    httpStatus: $httpStatus > 0 ? $httpStatus : null,
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );
            }

            if (!is_string($responseBody)) {
                throw new NovaPoshtaApiException(
                    message: 'Nova Poshta returned an unsupported response body.',
                    errorType: NovaPoshtaApiException::TYPE_INVALID_RESPONSE,
                    retryable: true,
                    httpStatus: $httpStatus > 0 ? $httpStatus : null,
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );
            }

            $response = $this->decodeResponse(
                responseBody: $responseBody,
                httpStatus: $httpStatus,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
            );

            $details = $this->extractApiDetails($response);

            if ($httpStatus < 200 || $httpStatus >= 300) {
                throw $this->createHttpException(
                    httpStatus: $httpStatus,
                    details: $details,
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );
            }

            if (($response['success'] ?? false) !== true) {
                throw $this->createApiRejectedException(
                    details: $details,
                    modelName: $modelName,
                    calledMethod: $calledMethod,
                    attempt: $attempt,
                );
            }

            return [
                'response' => $response,
                'httpStatus' => $httpStatus,
                'durationMs' => $durationMs,
            ];
        } finally {
            /*
             * Начиная с PHP 8 cURL handle является объектом CurlHandle.
             * Освобождение происходит при удалении последней ссылки.
             */
            unset($ch);
        }
    }

    private function createCurlException(int $curlErrorCode, string $curlErrorMessage, ?int $httpStatus, string $modelName, string $calledMethod, int $attempt): NovaPoshtaApiException
    {
        $message = $curlErrorMessage !== ''
            ? $curlErrorMessage
            : 'unknown cURL error';

        if (in_array($curlErrorCode, self::CURL_TIMEOUT_ERROR_CODES, true)) {
            return new NovaPoshtaApiException(
                message: 'Nova Poshta request timed out: ' . $message,
                errorType: NovaPoshtaApiException::TYPE_TIMEOUT,
                retryable: true,
                httpStatus: $httpStatus,
                curlErrorCode: $curlErrorCode,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
            );
        }

        if (in_array($curlErrorCode, self::CURL_CONNECTION_ERROR_CODES, true)) {
            return new NovaPoshtaApiException(
                message: 'Nova Poshta connection failed: ' . $message,
                errorType: NovaPoshtaApiException::TYPE_CONNECTION,
                retryable: true,
                httpStatus: $httpStatus,
                curlErrorCode: $curlErrorCode,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
            );
        }

        return new NovaPoshtaApiException(
            message: 'Nova Poshta transport error: ' . $message,
            errorType: NovaPoshtaApiException::TYPE_TRANSPORT,
            retryable: false,
            httpStatus: $httpStatus,
            curlErrorCode: $curlErrorCode,
            modelName: $modelName,
            calledMethod: $calledMethod,
            attempt: $attempt,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws NovaPoshtaApiException
     */
    private function decodeResponse(string $responseBody, int $httpStatus, string $modelName, string $calledMethod, int $attempt): array
    {
        try {
            $response = Json::decode($responseBody, true);
        } catch (Throwable $exception) {
            throw $this->createInvalidResponseException(
                message: $httpStatus > 0
                    ? sprintf(
                        'Nova Poshta returned invalid JSON with HTTP %d.',
                        $httpStatus
                    )
                    : 'Nova Poshta returned an invalid JSON response.',
                httpStatus: $httpStatus,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
                previous: $exception,
            );
        }

        if (!is_array($response)) {
            throw $this->createInvalidResponseException(
                message: $httpStatus > 0
                    ? sprintf(
                        'Nova Poshta returned an unexpected JSON value with HTTP %d.',
                        $httpStatus
                    )
                    : 'Nova Poshta returned an unexpected JSON value.',
                httpStatus: $httpStatus,
                modelName: $modelName,
                calledMethod: $calledMethod,
                attempt: $attempt,
            );
        }

        return $response;
    }

    private function createInvalidResponseException(string $message, int $httpStatus, string $modelName, string $calledMethod, int $attempt, ?Throwable $previous = null): NovaPoshtaApiException
    {
        $normalizedHttpStatus = $httpStatus > 0
            ? $httpStatus
            : null;

        if ($httpStatus === 429) {
            $errorType = NovaPoshtaApiException::TYPE_RATE_LIMIT;
            $retryable = true;
        } elseif ($httpStatus === 408) {
            $errorType = NovaPoshtaApiException::TYPE_TIMEOUT;
            $retryable = true;
        } elseif ($httpStatus >= 500) {
            $errorType = NovaPoshtaApiException::TYPE_HTTP_SERVER;
            $retryable = true;
        } elseif ($httpStatus >= 400) {
            $errorType = NovaPoshtaApiException::TYPE_HTTP_CLIENT;
            $retryable = false;
        } else {
            /*
             * Невалидный JSON при HTTP 2xx или без определённого HTTP-кода
             * может быть временным повреждённым ответом upstream.
             */
            $errorType = NovaPoshtaApiException::TYPE_INVALID_RESPONSE;
            $retryable = true;
        }

        return new NovaPoshtaApiException(
            message: $message,
            errorType: $errorType,
            retryable: $retryable,
            httpStatus: $normalizedHttpStatus,
            modelName: $modelName,
            calledMethod: $calledMethod,
            attempt: $attempt,
            previous: $previous,
        );
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array{
     *     errors: list<string>,
     *     errorCodes: list<string>,
     *     warnings: list<string>,
     *     warningCodes: list<string>,
     *     messageCodes: list<string>
     * }
     */
    private function extractApiDetails(array $response): array
    {
        return [
            'errors' => $this->normalizeScalarList(
                $response['errors'] ?? []
            ),
            'errorCodes' => $this->normalizeScalarList(
                $response['errorCodes'] ?? []
            ),
            'warnings' => $this->normalizeScalarList(
                $response['warnings'] ?? []
            ),
            'warningCodes' => $this->normalizeScalarList(
                $response['warningCodes'] ?? []
            ),
            'messageCodes' => $this->normalizeScalarList(
                $response['messageCodes'] ?? []
            ),
        ];
    }

    /**
     * API обычно возвращает массивы, но дополнительно поддерживаем
     * единичное scalar-значение.
     *
     * @return list<string>
     */
    private function normalizeScalarList(mixed $values): array
    {
        if (is_scalar($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $normalized = trim((string)$value);

            if ($normalized !== '') {
                $result[] = $normalized;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array{
     *     errors: list<string>,
     *     errorCodes: list<string>,
     *     warnings: list<string>,
     *     warningCodes: list<string>,
     *     messageCodes: list<string>
     * } $details
     */
    private function createHttpException(int $httpStatus, array $details, string $modelName, string $calledMethod, int $attempt): NovaPoshtaApiException
    {
        if ($httpStatus === 429) {
            $errorType = NovaPoshtaApiException::TYPE_RATE_LIMIT;
            $retryable = true;
        } elseif ($httpStatus === 408) {
            $errorType = NovaPoshtaApiException::TYPE_TIMEOUT;
            $retryable = true;
        } elseif ($httpStatus >= 500) {
            $errorType = NovaPoshtaApiException::TYPE_HTTP_SERVER;
            $retryable = true;
        } else {
            $errorType = NovaPoshtaApiException::TYPE_HTTP_CLIENT;
            $retryable = false;
        }

        return $this->createExceptionFromDetails(
            message: sprintf(
                'Nova Poshta request failed with HTTP %d: %s',
                $httpStatus,
                $this->formatApiDetails($details)
            ),
            errorType: $errorType,
            retryable: $retryable,
            details: $details,
            modelName: $modelName,
            calledMethod: $calledMethod,
            attempt: $attempt,
            httpStatus: $httpStatus,
        );
    }

    /**
     * @param array{
     *     errors: list<string>,
     *     errorCodes: list<string>,
     *     warnings: list<string>,
     *     warningCodes: list<string>,
     *     messageCodes: list<string>
     * } $details
     */
    private function createExceptionFromDetails(string $message, string $errorType, bool $retryable, array $details, string $modelName, string $calledMethod, int $attempt, ?int $httpStatus = null): NovaPoshtaApiException
    {
        return new NovaPoshtaApiException(
            message: $message,
            errorType: $errorType,
            retryable: $retryable,
            httpStatus: $httpStatus,
            errors: $details['errors'],
            errorCodes: $details['errorCodes'],
            warnings: $details['warnings'],
            warningCodes: $details['warningCodes'],
            messageCodes: $details['messageCodes'],
            modelName: $modelName,
            calledMethod: $calledMethod,
            attempt: $attempt,
        );
    }

    /**
     * @param array{
     *     errors: list<string>,
     *     errorCodes: list<string>,
     *     warnings: list<string>,
     *     warningCodes: list<string>,
     *     messageCodes: list<string>
     * } $details
     */
    private function formatApiDetails(array $details): string
    {
        $messages = array_merge(
            $details['errors'],
            $details['errorCodes'],
            $details['warnings'],
            $details['warningCodes'],
            $details['messageCodes'],
        );

        $messages = array_values(array_unique($messages));

        if ($messages === []) {
            return 'unknown API error';
        }

        return implode('; ', $messages);
    }

    /**
     * @param array{
     *     errors: list<string>,
     *     errorCodes: list<string>,
     *     warnings: list<string>,
     *     warningCodes: list<string>,
     *     messageCodes: list<string>
     * } $details
     */
    private function createApiRejectedException(array $details, string $modelName, string $calledMethod, int $attempt): NovaPoshtaApiException
    {
        $rateLimited = $this->containsRateLimitCode($details);

        return $this->createExceptionFromDetails(
            message: 'Nova Poshta API rejected the request: '
            . $this->formatApiDetails($details),
            errorType: $rateLimited
                ? NovaPoshtaApiException::TYPE_RATE_LIMIT
                : NovaPoshtaApiException::TYPE_API_REJECTED,
            retryable: $rateLimited,
            details: $details,
            modelName: $modelName,
            calledMethod: $calledMethod,
            attempt: $attempt,
        );
    }

    /**
     * @param array{
     *     errors: list<string>,
     *     errorCodes: list<string>,
     *     warnings: list<string>,
     *     warningCodes: list<string>,
     *     messageCodes: list<string>
     * } $details
     */
    private function containsRateLimitCode(array $details): bool
    {
        foreach ($details as $values) {
            if (in_array(self::API_RATE_LIMIT_CODE, $values, true)) {
                return true;
            }
        }

        return false;
    }

    private function calculateRetryDelayMs(int $attempt): int
    {
        if ($this->retryBaseDelayMs === 0) {
            return 0;
        }

        $exponentialDelayMs = (int)(
            $this->retryBaseDelayMs * (2 ** max(0, $attempt - 1))
        );

        $jitterMs = $this->retryJitterMs > 0
            ? mt_rand(0, $this->retryJitterMs)
            : 0;

        return min(
            $this->retryMaxDelayMs,
            $exponentialDelayMs + $jitterMs
        );
    }
}