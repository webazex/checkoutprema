<?php

declare(strict_types=1);

namespace common\services\keycrm;

use Yii;

final class KeyCrmRateLimiter
{
    private const CACHE_KEY_LAST_REQUEST_AT = 'keycrm:api:last-request-at-ms';
    private const LOCK_NAME = 'keycrm:api:rate-limit';
    private const LOG_CATEGORY = 'keycrm.sync.rate-limiter';

    public function __construct(
        private readonly int $minIntervalMs = 2000,
        private readonly int $lockTimeoutSeconds = 10,
    )
    {
    }

    public function beforeRequest(): void
    {
        if ($this->minIntervalMs <= 0) {
            return;
        }

        if (!Yii::$app->mutex->acquire(self::LOCK_NAME, $this->lockTimeoutSeconds)) {
            Yii::warning(
                KeyCrmSyncLogFormatter::event('rate-limiter', 'LOCK_FAILED_CONTINUE', [
                    'lock' => self::LOCK_NAME,
                    'minIntervalMs' => $this->minIntervalMs,
                    'lockTimeoutSeconds' => $this->lockTimeoutSeconds,
                ]),
                self::LOG_CATEGORY
            );

            return;
        }

        try {
            $nowMs = $this->nowMs();
            $lastRequestAtMs = (int)Yii::$app->cache->get(self::CACHE_KEY_LAST_REQUEST_AT);

            if ($lastRequestAtMs > 0) {
                $elapsedMs = $nowMs - $lastRequestAtMs;
                $sleepMs = $this->minIntervalMs - $elapsedMs;

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                    $nowMs = $this->nowMs();
                }
            }

            Yii::$app->cache->set(self::CACHE_KEY_LAST_REQUEST_AT, $nowMs, 3600);
        } finally {
            Yii::$app->mutex->release(self::LOCK_NAME);
        }
    }

    private function nowMs(): int
    {
        return (int)floor(microtime(true) * 1000);
    }
}