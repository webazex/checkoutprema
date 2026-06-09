<?php

declare(strict_types=1);

namespace common\jobs\keycrm;

use common\services\keycrm\KeyCrmCategorySyncService;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

final class KeyCrmImportCategoriesJob extends BaseObject implements JobInterface
{
    public int $maxPages = 0;
    public bool $linkProducts = true;
    public string $uniqueKey = '';

    public function execute($queue): void
    {
        $lockName = 'keycrm:import-categories';

        if (!Yii::$app->mutex->acquire($lockName, 0)) {
            Yii::warning([
                'message' => 'KeyCRM categories import skipped: lock is already acquired.',
                'lock' => $lockName,
                'uniqueKey' => $this->uniqueKey,
            ], __METHOD__);

            return;
        }

        try {
            /** @var KeyCrmCategorySyncService $service */
            $service = Yii::$container->get(KeyCrmCategorySyncService::class);

            $categoryStats = $service->importAllCategories(
                maxPages: $this->maxPages > 0 ? $this->maxPages : null,
            );

            $linkStats = null;

            if ($this->linkProducts) {
                $linkStats = $service->linkProductsToCategories();
            }

            Yii::info([
                'message' => 'KeyCRM categories import completed from queue.',
                'uniqueKey' => $this->uniqueKey,
                'categoryStats' => $categoryStats,
                'linkStats' => $linkStats,
            ], __METHOD__);
        } catch (Throwable $e) {
            Yii::error([
                'message' => 'KeyCRM categories import failed from queue.',
                'uniqueKey' => $this->uniqueKey,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);

            throw $e;
        } finally {
            Yii::$app->mutex->release($lockName);
        }
    }
}