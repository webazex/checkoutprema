<?php

declare(strict_types=1);

namespace common\integrations\novaposhta\exceptions;

use RuntimeException;
use Throwable;

final class NovaPoshtaRateLimiterException extends RuntimeException
{
    public const REASON_LOCK_TIMEOUT = 'lock_timeout';
    public const REASON_STATE_WRITE_FAILED = 'state_write_failed';

    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly string $lockName,
        public readonly int $lockTimeoutSeconds,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'reason' => $this->reason,
            'lockName' => $this->lockName,
            'lockTimeoutSeconds' => $this->lockTimeoutSeconds,
        ];
    }
}