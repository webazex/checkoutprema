<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\enums\delivery\DeliverySyncScope;
use common\jobs\delivery\DeliverySyncJob;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;
use Yii;
use yii\queue\Queue;

final readonly class DeliverySyncSchedulerService
{
    private const CHANNEL = 'delivery';
    private const LOG_CATEGORY = 'delivery.sync.scheduler';

    public function __construct(private DeliveryProviderRegistry $providers)
    {
    }

    public function schedule(string $providerCode, string $scope = DeliverySyncJob::SCOPE_ALL, string $scopeExternalRef = '', bool $resume = false, bool $force = false, int $limit = 500, int $staleAfterSeconds = 900): int|string|null
    {
        $providerCode = $this->providers->get($providerCode)->code();
        $scopeExternalRef = trim($scopeExternalRef);
        $scope = $this->normalizeScope($scope, $scopeExternalRef);

        if ($limit < 1) {
            throw new InvalidArgumentException('Delivery sync limit must be greater than zero.');
        }

        if ($staleAfterSeconds < 1) {
            throw new InvalidArgumentException('Delivery sync stale timeout must be greater than zero.');
        }

        /*
         * Provider-wide key блокирует одновременную постановку:
         *
         * all + points;
         * settlements + points;
         * global + scoped sync.
         */
        $uniqueKey = 'delivery:' . $providerCode;
        $lockName = 'delivery:schedule:' . sha1($uniqueKey);

        if (!Yii::$app->mutex->acquire($lockName, 3)) {
            throw new RuntimeException(sprintf('Can not acquire delivery scheduler lock "%s".', $lockName));
        }

        try {
            if (!$force && $this->hasActiveQueueJob($uniqueKey)) {
                Yii::info([
                    'event' => 'job_skipped_duplicate',
                    'providerCode' => $providerCode,
                    'scope' => $scope,
                    'scopeExternalRef' => $scopeExternalRef,
                    'uniqueKey' => $uniqueKey,
                ], self::LOG_CATEGORY);

                return null;
            }

            $job = new DeliverySyncJob([
                'providerCode' => $providerCode,
                'scope' => $scope,
                'scopeExternalRef' => $scopeExternalRef,
                'resume' => $resume,
                'limit' => $limit,
                'staleAfterSeconds' => $staleAfterSeconds,
                'uniqueKey' => $uniqueKey,
            ]);

            /** @var Queue $queue */
            $queue = Yii::$app->get('deliveryQueue');
            $jobId = $queue->push($job);

            Yii::info([
                'event' => 'job_pushed',
                'providerCode' => $providerCode,
                'scope' => $scope,
                'scopeExternalRef' => $scopeExternalRef,
                'resume' => $resume,
                'force' => $force,
                'limit' => $limit,
                'staleAfterSeconds' => $staleAfterSeconds,
                'uniqueKey' => $uniqueKey,
                'jobId' => $jobId,
            ], self::LOG_CATEGORY);

            return $jobId;
        } catch (Throwable $exception) {
            Yii::error([
                'event' => 'job_push_failed',
                'providerCode' => $providerCode,
                'scope' => $scope,
                'scopeExternalRef' => $scopeExternalRef,
                'uniqueKey' => $uniqueKey,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ], self::LOG_CATEGORY);

            throw $exception;
        } finally {
            Yii::$app->mutex->release($lockName);
        }
    }

    private function hasActiveQueueJob(string $uniqueKey): bool
    {
        $payloadLike = '%' . addcslashes($uniqueKey, '%_\\') . '%';

        return (bool)Yii::$app->db->createCommand(
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

    private function normalizeScope(string $scope, string $scopeExternalRef): string
    {
        $scope = strtolower(trim($scope));

        if ($scope === DeliverySyncJob::SCOPE_ALL) {
            if ($scopeExternalRef !== '') {
                throw new InvalidArgumentException('Full delivery sync does not support scopeExternalRef.');
            }

            return $scope;
        }

        $scopeEnum = DeliverySyncScope::tryFrom($scope);

        if ($scopeEnum === null) {
            throw new InvalidArgumentException(sprintf('Unknown delivery sync scope "%s".', $scope));
        }

        if ($scopeEnum === DeliverySyncScope::SCHEDULES) {
            throw new LogicException('Schedule sync is performed as part of point sync.');
        }

        if ($scopeEnum === DeliverySyncScope::AREAS && $scopeExternalRef !== '') {
            throw new InvalidArgumentException('Area sync does not support scopeExternalRef.');
        }

        return $scopeEnum->value;
    }
}