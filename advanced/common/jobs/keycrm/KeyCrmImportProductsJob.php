<?php

declare(strict_types=1);

namespace common\jobs\keycrm;

use common\services\keycrm\KeyCrmProductImportService;
use common\services\keycrm\KeyCrmSyncLogFormatter;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

final class KeyCrmImportProductsJob extends BaseObject implements JobInterface
{
    private const LOCK_NAME = 'keycrm:import-products';
    private const LOG_CATEGORY = 'keycrm.sync.products';

    public int $maxPages = 0;
    public bool $withCustomFields = true;
    public string $uniqueKey = '';

    public function execute($queue): void
    {
        unset($queue);

        if (!Yii::$app->mutex->acquire(self::LOCK_NAME, 0)) {
            Yii::warning(
                KeyCrmSyncLogFormatter::event('products', 'SKIP_LOCKED', [
                    'key' => $this->uniqueKey,
                    'lock' => self::LOCK_NAME,
                    'maxPages' => $this->maxPages,
                    'withCustomFields' => $this->withCustomFields,
                ]),
                self::LOG_CATEGORY
            );

            return;
        }

        try {
            /** @var KeyCrmProductImportService $service */
            $service = Yii::$container->get(KeyCrmProductImportService::class);

            $stats = $service->importAllProducts(
                withCustomFields: $this->withCustomFields,
                maxPages: $this->maxPages > 0 ? $this->maxPages : null,
            );

            Yii::info(
                KeyCrmSyncLogFormatter::event('products', 'DONE', array_merge(
                    [
                        'key' => $this->uniqueKey,
                        'maxPages' => $this->maxPages,
                        'withCustomFields' => $this->withCustomFields,
                    ],
                    $stats,
                )),
                self::LOG_CATEGORY
            );
        } catch (Throwable $e) {
            Yii::error(
                KeyCrmSyncLogFormatter::event('products', 'FAILED', [
                    'key' => $this->uniqueKey,
                    'maxPages' => $this->maxPages,
                    'withCustomFields' => $this->withCustomFields,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]),
                self::LOG_CATEGORY
            );

            throw $e;
        } finally {
            Yii::$app->mutex->release(self::LOCK_NAME);
        }
    }
}