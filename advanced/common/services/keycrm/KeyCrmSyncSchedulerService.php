<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\jobs\keycrm\KeyCrmImportCategoriesJob;
use common\jobs\keycrm\KeyCrmImportProductsJob;
use RuntimeException;
use Yii;

final class KeyCrmSyncSchedulerService
{
    private const CHANNEL = 'keycrm';

    public function scheduleProducts(int $maxPages = 0, bool $withCustomFields = true): int|string|null
    {
        $uniqueKey = $this->buildUniqueKey('products', [
            'maxPages' => $maxPages,
            'withCustomFields' => $withCustomFields,
        ]);

        return $this->pushUnique(
            uniqueKey: $uniqueKey,
            lockName: 'keycrm:schedule:products',
            job: new KeyCrmImportProductsJob([
                'maxPages' => $maxPages,
                'withCustomFields' => $withCustomFields,
                'uniqueKey' => $uniqueKey,
            ]),
        );
    }

    public function scheduleCategories(int $maxPages = 0, bool $linkProducts = true): int|string|null
    {
        $uniqueKey = $this->buildUniqueKey('categories', [
            'maxPages' => $maxPages,
            'linkProducts' => $linkProducts,
        ]);

        return $this->pushUnique(
            uniqueKey: $uniqueKey,
            lockName: 'keycrm:schedule:categories',
            job: new KeyCrmImportCategoriesJob([
                'maxPages' => $maxPages,
                'linkProducts' => $linkProducts,
                'uniqueKey' => $uniqueKey,
            ]),
        );
    }

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

    private function pushUnique(string $uniqueKey, string $lockName, object $job): int|string|null
    {
        if (!Yii::$app->mutex->acquire($lockName, 3)) {
            throw new RuntimeException("Can not acquire scheduler lock: {$lockName}");
        }

        try {
            if ($this->hasActiveJob($uniqueKey)) {
                Yii::info([
                    'message' => 'KeyCRM sync job was not pushed: duplicate active job exists.',
                    'uniqueKey' => $uniqueKey,
                ], __METHOD__);

                return null;
            }

            return Yii::$app->queue->push($job);
        } finally {
            Yii::$app->mutex->release($lockName);
        }
    }

    private function hasActiveJob(string $uniqueKey): bool
    {
        $payloadLike = '%' . addcslashes($uniqueKey, '%_\\') . '%';

        return (bool) Yii::$app->db
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

    private function buildUniqueKey(string $type, array $params): string
    {
        ksort($params);

        return 'keycrm:' . $type . ':' . md5(json_encode($params, JSON_THROW_ON_ERROR));
    }
}