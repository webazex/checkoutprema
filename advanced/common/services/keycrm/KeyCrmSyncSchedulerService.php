<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\jobs\keycrm\KeyCrmImportCategoriesJob;
use common\jobs\keycrm\KeyCrmImportProductsJob;
use RuntimeException;
use Throwable;
use Yii;

final class KeyCrmSyncSchedulerService
{
    private const CHANNEL = 'keycrm';
    private const LOG_CATEGORY = 'keycrm.sync.scheduler';

    private const SYNC_TYPE_PRODUCTS = 'products';
    private const SYNC_TYPE_CATEGORIES = 'categories';

    private const ACTIVE_RUN_MAX_AGE_SECONDS = 3600;

    /**
     * @return array{categories:int|string|null, products:int|string|null}
     */
    public function scheduleFull(int $maxPages = 0): array
    {
        return [
            'categories' => $this->scheduleCategories($maxPages, true),
            'products' => $this->scheduleProducts($maxPages, true),
        ];
    }

    public function scheduleCategories(int $maxPages = 0, bool $linkProducts = true): int|string|null
    {
        $syncType = self::SYNC_TYPE_CATEGORIES;

        $params = [
            'maxPages' => $maxPages,
            'linkProducts' => $linkProducts,
        ];

        $uniqueKey = $this->buildUniqueKey($syncType, $params);

        return $this->pushUnique(
            syncType: $syncType,
            uniqueKey: $uniqueKey,
            lockName: 'keycrm:schedule:categories',
            params: $params,
            jobFactory: static fn(int $runId): KeyCrmImportCategoriesJob => new KeyCrmImportCategoriesJob([
                'runId' => $runId,
                'maxPages' => $maxPages,
                'linkProducts' => $linkProducts,
                'uniqueKey' => $uniqueKey,
            ]),
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildUniqueKey(string $type, array $params): string
    {
        ksort($params);

        return 'keycrm:' . $type . ':' . md5(json_encode($params, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $params
     * @param callable(int): object $jobFactory
     */
    private function pushUnique(
        string   $syncType,
        string   $uniqueKey,
        string   $lockName,
        array    $params,
        callable $jobFactory,
    ): int|string|null
    {
        if (!Yii::$app->mutex->acquire($lockName, 3)) {
            Yii::warning(
                KeyCrmSyncLogFormatter::event('scheduler', 'LOCK_FAILED', [
                    'type' => $syncType,
                    'key' => $uniqueKey,
                    'lock' => $lockName,
                ]),
                self::LOG_CATEGORY
            );

            throw new RuntimeException("Can not acquire scheduler lock: {$lockName}");
        }

        /** @var KeyCrmSyncRunLogger $runLogger */
        $runLogger = Yii::$container->get(KeyCrmSyncRunLogger::class);

        try {
            if ($runLogger->hasActiveRun($syncType, $uniqueKey, self::ACTIVE_RUN_MAX_AGE_SECONDS)) {
                $reason = 'Duplicate active sync run exists.';

                $runLogger->skippedAttempt(
                    syncType: $syncType,
                    uniqueKey: $uniqueKey,
                    reason: $reason,
                    params: $params,
                );

                Yii::info(
                    KeyCrmSyncLogFormatter::event('scheduler', 'SKIP_DUPLICATE_RUN', [
                        'type' => $syncType,
                        'key' => $uniqueKey,
                        'reason' => $reason,
                    ]),
                    self::LOG_CATEGORY
                );

                return null;
            }

            if ($this->hasActiveQueueJob($uniqueKey)) {
                $reason = 'Duplicate active queue job exists.';

                $runLogger->skippedAttempt(
                    syncType: $syncType,
                    uniqueKey: $uniqueKey,
                    reason: $reason,
                    params: $params,
                );

                Yii::info(
                    KeyCrmSyncLogFormatter::event('scheduler', 'SKIP_DUPLICATE_QUEUE_JOB', [
                        'type' => $syncType,
                        'key' => $uniqueKey,
                        'reason' => $reason,
                    ]),
                    self::LOG_CATEGORY
                );

                return null;
            }

            $runId = $runLogger->queued(
                syncType: $syncType,
                uniqueKey: $uniqueKey,
                params: $params,
            );

            try {
                $job = $jobFactory($runId);
                $jobId = Yii::$app->queue->push($job);

                $runLogger->attachQueueJobId($runId, $jobId);

                Yii::info(
                    KeyCrmSyncLogFormatter::event('scheduler', 'PUSHED', [
                        'type' => $syncType,
                        'key' => $uniqueKey,
                        'runId' => $runId,
                        'jobId' => $jobId,
                    ]),
                    self::LOG_CATEGORY
                );

                return $jobId;
            } catch (Throwable $e) {
                $runLogger->failed($runId, $e);

                Yii::error(
                    KeyCrmSyncLogFormatter::event('scheduler', 'PUSH_FAILED', [
                        'type' => $syncType,
                        'key' => $uniqueKey,
                        'runId' => $runId,
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]),
                    self::LOG_CATEGORY
                );

                throw $e;
            }
        } finally {
            Yii::$app->mutex->release($lockName);
        }
    }

    private function hasActiveQueueJob(string $uniqueKey): bool
    {
        $payloadLike = '%' . addcslashes($uniqueKey, '%_\\') . '%';

        return (bool)Yii::$app->db
            ->createCommand(
                <<<SQL
SELECT EXISTS(
    SELECT 1
    FROM {{%queue}}
    WHERE [[channel]] = :channel
      AND [[done_at]] IS NULL
      AND CAST([[job]] AS CHAR) LIKE :payloadLike
    LIMIT 1
)
SQL
            )
            ->bindValue(':channel', self::CHANNEL)
            ->bindValue(':payloadLike', $payloadLike)
            ->queryScalar();
    }

    public function scheduleProducts(int $maxPages = 0, bool $withCustomFields = true): int|string|null
    {
        $syncType = self::SYNC_TYPE_PRODUCTS;

        $params = [
            'maxPages' => $maxPages,
            'withCustomFields' => $withCustomFields,
        ];

        $uniqueKey = $this->buildUniqueKey($syncType, $params);

        return $this->pushUnique(
            syncType: $syncType,
            uniqueKey: $uniqueKey,
            lockName: 'keycrm:schedule:products',
            params: $params,
            jobFactory: static fn(int $runId): KeyCrmImportProductsJob => new KeyCrmImportProductsJob([
                'runId' => $runId,
                'maxPages' => $maxPages,
                'withCustomFields' => $withCustomFields,
                'uniqueKey' => $uniqueKey,
            ]),
        );
    }
}