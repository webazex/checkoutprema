<?php

declare(strict_types=1);

namespace common\jobs\keycrm;

use common\services\keycrm\KeyCrmCategorySyncService;
use common\services\keycrm\KeyCrmSyncLogFormatter;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

final class KeyCrmImportCategoriesJob extends BaseObject implements JobInterface
{
    private const LOCK_NAME = 'keycrm:import-categories';
    private const LOG_CATEGORY = 'keycrm.sync.categories';

    public int $maxPages = 0;
    public bool $linkProducts = true;
    public string $uniqueKey = '';

    public function execute($queue): void
    {
        unset($queue);

        if (!Yii::$app->mutex->acquire(self::LOCK_NAME, 0)) {
            Yii::warning(
                KeyCrmSyncLogFormatter::event('categories', 'SKIP_LOCKED', [
                    'key' => $this->uniqueKey,
                    'lock' => self::LOCK_NAME,
                    'maxPages' => $this->maxPages,
                    'linkProducts' => $this->linkProducts,
                ]),
                self::LOG_CATEGORY
            );

            return;
        }

        try {
            /** @var KeyCrmCategorySyncService $service */
            $service = Yii::$container->get(KeyCrmCategorySyncService::class);

            $categoryStats = $service->importAllCategories(
                maxPages: $this->maxPages > 0 ? $this->maxPages : null,
            );

            Yii::info(
                KeyCrmSyncLogFormatter::event('categories', 'DONE', array_merge(
                    [
                        'key' => $this->uniqueKey,
                        'maxPages' => $this->maxPages,
                        'linkProducts' => $this->linkProducts,
                    ],
                    $categoryStats,
                )),
                self::LOG_CATEGORY
            );

            if ($this->linkProducts) {
                $linkStats = $service->linkProductsToCategories();

                Yii::info(
                    KeyCrmSyncLogFormatter::event('category-products', 'LINK_DONE', array_merge(
                        [
                            'key' => $this->uniqueKey,
                        ],
                        $linkStats,
                    )),
                    self::LOG_CATEGORY
                );
            }
        } catch (Throwable $e) {
            Yii::error(
                KeyCrmSyncLogFormatter::event('categories', 'FAILED', [
                    'key' => $this->uniqueKey,
                    'maxPages' => $this->maxPages,
                    'linkProducts' => $this->linkProducts,
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