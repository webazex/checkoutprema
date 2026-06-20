<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\dto\delivery\DeliverySyncStateReadDto;
use common\enums\delivery\DeliverySyncScope;
use common\enums\delivery\DeliverySyncStatus;
use common\storages\delivery\DeliverySyncStateStorage;
use InvalidArgumentException;
use Yii;

final readonly class DeliverySyncStatusService
{
    private const QUEUE_CHANNEL = 'delivery';

    public function __construct(
        private DeliveryProviderRegistry $providers,
        private DeliverySyncStateStorage $states,
    )
    {
    }

    /**
     * @return array{
     *     providerCode: string,
     *     providerName: string,
     *     generatedAt: int,
     *     staleAfterSeconds: int,
     *     healthy: bool,
     *     scopes: array<string, array{
     *         state: DeliverySyncStateReadDto|null,
     *         stale: bool,
     *         heartbeatAge: int|null
     *     }>,
     *     queue: array{
     *         waiting: int,
     *         delayed: int,
     *         reserved: int,
     *         done: int
     *     }
     * }
     */
    public function snapshot(string $providerCode, int $staleAfterSeconds = 900): array
    {
        if ($staleAfterSeconds < 1) {
            throw new InvalidArgumentException(
                'Delivery sync stale timeout must be greater than zero.'
            );
        }

        $provider = $this->providers->get($providerCode);
        $providerCode = $provider->code();
        $now = time();
        $healthy = true;
        $scopes = [];

        foreach ($this->syncScopes() as $scope) {
            $state = $this->states->find($providerCode, $scope);
            $heartbeatAge = $this->heartbeatAge($state, $now);
            $stale = $this->isStale($state, $heartbeatAge, $staleAfterSeconds);

            if ($state?->status === DeliverySyncStatus::FAILED || $stale) {
                $healthy = false;
            }

            $scopes[$scope->value] = [
                'state' => $state,
                'stale' => $stale,
                'heartbeatAge' => $heartbeatAge,
            ];
        }

        return [
            'providerCode' => $providerCode,
            'providerName' => $provider->name(),
            'generatedAt' => $now,
            'staleAfterSeconds' => $staleAfterSeconds,
            'healthy' => $healthy,
            'scopes' => $scopes,
            'queue' => $this->queueStatus($now),
        ];
    }

    /**
     * @return list<DeliverySyncScope>
     */
    private function syncScopes(): array
    {
        return [
            DeliverySyncScope::AREAS,
            DeliverySyncScope::SETTLEMENTS,
            DeliverySyncScope::POINTS,
        ];
    }

    private function heartbeatAge(?DeliverySyncStateReadDto $state, int $now): ?int
    {
        if ($state?->heartbeatAt === null) {
            return null;
        }

        return max(0, $now - $state->heartbeatAt);
    }

    private function isStale(
        ?DeliverySyncStateReadDto $state,
        ?int                      $heartbeatAge,
        int                       $staleAfterSeconds
    ): bool
    {
        if ($state?->status !== DeliverySyncStatus::RUNNING) {
            return false;
        }

        return $heartbeatAge === null || $heartbeatAge > $staleAfterSeconds;
    }

    /**
     * @return array{
     *     waiting: int,
     *     delayed: int,
     *     reserved: int,
     *     done: int
     * }
     */
    private function queueStatus(int $now): array
    {
        $row = Yii::$app->db->createCommand(
            <<<SQL
SELECT
    COALESCE(SUM(
        CASE
            WHEN [[done_at]] IS NULL
                AND [[reserved_at]] IS NULL
                AND [[pushed_at]] + COALESCE([[delay]], 0) <= :now
            THEN 1
            ELSE 0
        END
    ), 0) AS [[waiting]],
    COALESCE(SUM(
        CASE
            WHEN [[done_at]] IS NULL
                AND [[reserved_at]] IS NULL
                AND [[pushed_at]] + COALESCE([[delay]], 0) > :now
            THEN 1
            ELSE 0
        END
    ), 0) AS [[delayed]],
    COALESCE(SUM(
        CASE
            WHEN [[done_at]] IS NULL
                AND [[reserved_at]] IS NOT NULL
            THEN 1
            ELSE 0
        END
    ), 0) AS [[reserved]],
    COALESCE(SUM(
        CASE
            WHEN [[done_at]] IS NOT NULL
            THEN 1
            ELSE 0
        END
    ), 0) AS [[done]]
FROM {{%queue}}
WHERE [[channel]] = :channel
SQL
        )
            ->bindValue(':now', $now)
            ->bindValue(':channel', self::QUEUE_CHANNEL)
            ->queryOne();

        return [
            'waiting' => (int)($row['waiting'] ?? 0),
            'delayed' => (int)($row['delayed'] ?? 0),
            'reserved' => (int)($row['reserved'] ?? 0),
            'done' => (int)($row['done'] ?? 0),
        ];
    }
}