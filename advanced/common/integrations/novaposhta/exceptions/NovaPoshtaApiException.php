<?php


declare(strict_types=1);

namespace common\integrations\novaposhta\exceptions;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class NovaPoshtaApiException extends RuntimeException
{
    public const TYPE_RATE_LIMIT = 'rate_limit';
    public const TYPE_RATE_LIMITER = 'rate_limiter';
    public const TYPE_TIMEOUT = 'timeout';
    public const TYPE_CONNECTION = 'connection';
    public const TYPE_HTTP_SERVER = 'http_server';
    public const TYPE_HTTP_CLIENT = 'http_client';
    public const TYPE_API_REJECTED = 'api_rejected';
    public const TYPE_INVALID_RESPONSE = 'invalid_response';
    public const TYPE_TRANSPORT = 'transport';

    /**
     * @param list<string> $errors
     * @param list<string> $errorCodes
     * @param list<string> $warnings
     * @param list<string> $warningCodes
     * @param list<string> $messageCodes
     */
    public function __construct(
        string                  $message,
        public readonly string  $errorType,
        public readonly bool    $retryable,
        public readonly ?int    $httpStatus = null,
        public readonly ?int    $curlErrorCode = null,
        public readonly array   $errors = [],
        public readonly array   $errorCodes = [],
        public readonly array   $warnings = [],
        public readonly array   $warningCodes = [],
        public readonly array   $messageCodes = [],
        public readonly ?string $modelName = null,
        public readonly ?string $calledMethod = null,
        public readonly int     $attempt = 1,
        ?Throwable              $previous = null,
    )
    {
        if ($attempt < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta API exception attempt must be greater than zero.'
            );
        }

        parent::__construct($message, 0, $previous);
    }

    public function primaryCode(): ?string
    {
        return $this->errorCodes[0]
            ?? $this->warningCodes[0]
            ?? $this->messageCodes[0]
            ?? null;
    }

    public function hasApiCode(string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        return in_array($code, $this->errorCodes, true)
            || in_array($code, $this->warningCodes, true)
            || in_array($code, $this->messageCodes, true);
    }

    public function withAttempt(int $attempt): self
    {
        return new self(
            message: $this->getMessage(),
            errorType: $this->errorType,
            retryable: $this->retryable,
            httpStatus: $this->httpStatus,
            curlErrorCode: $this->curlErrorCode,
            errors: $this->errors,
            errorCodes: $this->errorCodes,
            warnings: $this->warnings,
            warningCodes: $this->warningCodes,
            messageCodes: $this->messageCodes,
            modelName: $this->modelName,
            calledMethod: $this->calledMethod,
            attempt: $attempt,
            previous: $this,
        );
    }

    /**
     * Безопасный контекст для логов.
     *
     * API key, request payload и полный response body сюда намеренно
     * не включаются.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'type' => $this->errorType,
            'retryable' => $this->retryable,
            'attempt' => $this->attempt,
            'httpStatus' => $this->httpStatus,
            'curlErrorCode' => $this->curlErrorCode,
            'primaryCode' => $this->primaryCode(),
            'errorCodes' => $this->errorCodes,
            'warningCodes' => $this->warningCodes,
            'messageCodes' => $this->messageCodes,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'modelName' => $this->modelName,
            'calledMethod' => $this->calledMethod,
        ];
    }
}