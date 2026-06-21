<?php

declare(strict_types=1);

namespace common\services\novaposhta;

use common\integrations\novaposhta\exceptions\NovaPoshtaRateLimiterException;
use InvalidArgumentException;
use Yii;

final class NovaPoshtaRateLimiter
{
    private const CACHE_KEY_LAST_REQUEST_AT_MS =
        'nova-poshta:api:last-request-at-ms';

    private const LOCK_NAME =
        'nova-poshta:api:rate-limit';

    private const CACHE_TTL_SECONDS = 86400;

    private const LOG_CATEGORY =
        'novaposhta.api.rate-limiter';

    public function __construct(
        private readonly int $minIntervalMs = 1000,
        private readonly int $lockTimeoutSeconds = 10,
    )
    {
        if ($minIntervalMs < 0) {
            throw new InvalidArgumentException(
                'Nova Poshta API minimum interval must not be negative.'
            );
        }

        if ($lockTimeoutSeconds < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta rate limiter lock timeout must be greater than zero.'
            );
        }
    }

    /**
     * Вызывается непосредственно перед каждым внешним API-запросом.
     *
     * Mutex остаётся захваченным на время проверки timestamp,
     * ожидания интервала и записи нового timestamp.
     *
     * Благодаря этому параллельные workers не смогут одновременно
     * пройти проверку и выполнить запросы в один момент.
     */
    public function beforeRequest(): void
    {
        if ($this->minIntervalMs === 0) {
            return;
        }

        $mutex = Yii::$app->mutex;

        if (!$mutex->acquire(
            self::LOCK_NAME,
            $this->lockTimeoutSeconds
        )) {
            $exception = new NovaPoshtaRateLimiterException(
                message: sprintf(
                    'Failed to acquire Nova Poshta API rate limiter lock within %d second(s).',
                    $this->lockTimeoutSeconds
                ),
                reason: NovaPoshtaRateLimiterException::REASON_LOCK_TIMEOUT,
                lockName: self::LOCK_NAME,
                lockTimeoutSeconds: $this->lockTimeoutSeconds,
            );

            Yii::warning(
                NovaPoshtaLogFormatter::event(
                    component: 'rate-limiter',
                    status: 'LOCK_TIMEOUT',
                    context: $exception->context()
                ),
                self::LOG_CATEGORY
            );

            throw $exception;
        }

        try {
            $this->waitForAvailableSlot();

            $requestStartedAtMs = $this->nowMs();

            $saved = Yii::$app->cache->set(
                self::CACHE_KEY_LAST_REQUEST_AT_MS,
                $requestStartedAtMs,
                self::CACHE_TTL_SECONDS
            );

            if (!$saved) {
                $exception = new NovaPoshtaRateLimiterException(
                    message: 'Failed to persist Nova Poshta API rate limiter state.',
                    reason: NovaPoshtaRateLimiterException::REASON_STATE_WRITE_FAILED,
                    lockName: self::LOCK_NAME,
                    lockTimeoutSeconds: $this->lockTimeoutSeconds,
                );

                Yii::error(
                    NovaPoshtaLogFormatter::event(
                        component: 'rate-limiter',
                        status: 'STATE_WRITE_FAILED',
                        context: $exception->context()
                    ),
                    self::LOG_CATEGORY
                );

                throw $exception;
            }
        } finally {
            $mutex->release(self::LOCK_NAME);
        }
    }

    private function waitForAvailableSlot(): void
    {
        $lastRequestAtMs = $this->getLastRequestAtMs();

        if ($lastRequestAtMs === null) {
            return;
        }

        $nowMs = $this->nowMs();
        $elapsedMs = max(0, $nowMs - $lastRequestAtMs);
        $sleepMs = $this->minIntervalMs - $elapsedMs;

        if ($sleepMs <= 0) {
            return;
        }

        Yii::info(
            NovaPoshtaLogFormatter::event(
                component: 'rate-limiter',
                status: 'WAIT',
                context: [
                    'sleepMs' => $sleepMs,
                    'minIntervalMs' => $this->minIntervalMs,
                    'elapsedMs' => $elapsedMs,
                ]
            ),
            self::LOG_CATEGORY
        );

        usleep($sleepMs * 1000);
    }

    private function getLastRequestAtMs(): ?int
    {
        $value = Yii::$app->cache->get(
            self::CACHE_KEY_LAST_REQUEST_AT_MS
        );

        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $timestamp = (int)$value;

        return $timestamp > 0 ? $timestamp : null;
    }

    private function nowMs(): int
    {
        return (int)floor(microtime(true) * 1000);
    }
}