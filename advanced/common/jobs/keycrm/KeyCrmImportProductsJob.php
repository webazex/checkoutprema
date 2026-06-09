<?php

declare(strict_types=1);

namespace common\jobs\keycrm;

use common\services\keycrm\KeyCrmProductImportService;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

final class KeyCrmImportProductsJob extends BaseObject implements JobInterface
{
    public int $maxPages = 0;
    public bool $withCustomFields = true;
    public string $uniqueKey = '';

    public function execute($queue): void
    {
        $lockName = 'keycrm:import-products';

        if (!Yii::$app->mutex->acquire($lockName, 0)) {
            Yii::warning([
                'message' => 'KeyCRM products import skipped: lock is already acquired.',
                'lock' => $lockName,
                'uniqueKey' => $this->uniqueKey,
            ], __METHOD__);

            return;
        }

        try {
            /** @var KeyCrmProductImportService $service */
            $service = Yii::$container->get(KeyCrmProductImportService::class);

            $stats = $service->importAllProducts(
                withCustomFields: $this->withCustomFields,
                maxPages: $this->maxPages > 0 ? $this->maxPages : null,
            );

            Yii::info([
                'message' => 'KeyCRM products import completed from queue.',
                'uniqueKey' => $this->uniqueKey,
                'stats' => $stats,
            ], __METHOD__);
        } catch (Throwable $e) {
            Yii::error([
                'message' => 'KeyCRM products import failed from queue.',
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