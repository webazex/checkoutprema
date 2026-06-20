<?php

declare(strict_types=1);

namespace common\jobs\delivery;

use common\enums\delivery\DeliverySyncScope;
use common\services\delivery\DeliverySyncService;
use InvalidArgumentException;
use LogicException;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

final class DeliverySyncJob extends BaseObject implements JobInterface
{
    public const SCOPE_ALL = 'all';

    private const LOG_CATEGORY = 'delivery.sync.job';

    public string $providerCode = '';
    public string $scope = self::SCOPE_ALL;
    public string $scopeExternalRef = '';
    public bool $resume = false;
    public int $limit = 500;
    public int $staleAfterSeconds = 900;
    public string $uniqueKey = '';

    public function execute($queue): void
    {
        unset($queue);

        $providerCode = trim($this->providerCode);
        $scopeExternalRef = trim($this->scopeExternalRef);
        $lockName = 'delivery:sync:' . sha1($providerCode);

        if (!Yii::$app->mutex->acquire($lockName, 0)) {
            Yii::warning([
                'event' => 'job_skipped_locked',
                'providerCode' => $providerCode,
                'scope' => $this->scope,
                'scopeExternalRef' => $scopeExternalRef,
                'uniqueKey' => $this->uniqueKey,
                'lockName' => $lockName,
            ], self::LOG_CATEGORY);

            return;
        }

        try {
            $scopes = $this->resolveScopes($scopeExternalRef);

            Yii::info([
                'event' => 'job_started',
                'providerCode' => $providerCode,
                'scope' => $this->scope,
                'scopeExternalRef' => $scopeExternalRef,
                'resume' => $this->resume,
                'limit' => $this->limit,
                'staleAfterSeconds' => $this->staleAfterSeconds,
                'uniqueKey' => $this->uniqueKey,
            ], self::LOG_CATEGORY);

            /** @var DeliverySyncService $service */
            $service = Yii::$container->get(DeliverySyncService::class);

            foreach ($scopes as $scope) {
                $service->sync(providerCode: $providerCode, scope: $scope, scopeExternalRef: $scopeExternalRef, resume: $this->resume, limit: $this->limit, staleAfterSeconds: $this->staleAfterSeconds);
            }

            Yii::info([
                'event' => 'job_completed',
                'providerCode' => $providerCode,
                'scope' => $this->scope,
                'scopeExternalRef' => $scopeExternalRef,
                'uniqueKey' => $this->uniqueKey,
            ], self::LOG_CATEGORY);
        } catch (Throwable $exception) {
            Yii::error([
                'event' => 'job_failed',
                'providerCode' => $providerCode,
                'scope' => $this->scope,
                'scopeExternalRef' => $scopeExternalRef,
                'uniqueKey' => $this->uniqueKey,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ], self::LOG_CATEGORY);

            throw $exception;
        } finally {
            Yii::$app->mutex->release($lockName);
        }
    }

    /**
     * @return list<DeliverySyncScope>
     */
    private function resolveScopes(string $scopeExternalRef): array
    {
        $scope = strtolower(trim($this->scope));

        if ($this->limit < 1) {
            throw new InvalidArgumentException('Delivery sync job limit must be greater than zero.');
        }

        if ($this->staleAfterSeconds < 1) {
            throw new InvalidArgumentException('Delivery sync job stale timeout must be greater than zero.');
        }

        if ($scope === self::SCOPE_ALL) {
            if ($scopeExternalRef !== '') {
                throw new InvalidArgumentException('Full delivery sync does not support scopeExternalRef.');
            }

            return [
                DeliverySyncScope::AREAS,
                DeliverySyncScope::SETTLEMENTS,
                DeliverySyncScope::POINTS,
            ];
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

        return [$scopeEnum];
    }
}