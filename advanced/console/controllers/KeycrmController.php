<?php

declare(strict_types=1);

namespace console\controllers;

use common\services\keycrm\KeyCrmProductImportService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use common\services\keycrm\KeyCrmArchivedProductCleanupService;
use common\services\keycrm\KeyCrmCategorySyncService;

final class KeycrmController extends Controller
{
    public function actionImportProducts(int $maxPages = 0): int
    {
        /** @var KeyCrmProductImportService $service */
        $service = Yii::$container->get(KeyCrmProductImportService::class);

        $stats = $service->importAllProducts(
            withCustomFields: true,
            maxPages: $maxPages > 0 ? $maxPages : null,
        );

        $this->stdout("KeyCRM products import completed.\n");
        $this->stdout("Pages: {$stats['pages']}\n");
        $this->stdout("Processed: {$stats['processed']}\n");
        $this->stdout("Created: {$stats['created']}\n");
        $this->stdout("Updated: {$stats['updated']}\n");
        $this->stdout("Mappings created: {$stats['mapped']}\n");
        $this->stdout("Archived: {$stats['archived']}\n");
        $this->stdout("Restored: {$stats['restored']}\n");
        $this->stdout("Unchanged: {$stats['unchanged']}\n");
        $this->stdout("Orphan repaired: {$stats['orphanRepaired']}\n");
        $this->stdout("Orphan removed: {$stats['orphanRemoved']}\n");
        $this->stdout("Errors: {$stats['errors']}\n");

        return ExitCode::OK;
    }

    public function actionCleanupArchivedProducts(int $days = 7): int
    {
        /** @var KeyCrmArchivedProductCleanupService $service */
        $service = Yii::$container->get(KeyCrmArchivedProductCleanupService::class);

        $stats = $service->cleanup($days > 0 ? $days : 7);

        $this->stdout("KeyCRM archived products cleanup completed.\n");
        $this->stdout('Scanned: ' . $stats['scanned'] . "\n");
        $this->stdout('Eligible: ' . $stats['eligible'] . "\n");
        $this->stdout('Deleted: ' . $stats['deleted'] . "\n");
        $this->stdout('Skipped (cart items): ' . $stats['skippedCartItems'] . "\n");
        $this->stdout('Skipped (order items): ' . $stats['skippedOrderItems'] . "\n");
        $this->stdout('Skipped (missing archived_at): ' . $stats['skippedMissingArchivedAt'] . "\n");
        $this->stdout('Errors: ' . $stats['errors'] . "\n");

        return ExitCode::OK;
    }

    public function actionImportCategories(int $maxPages = 0): int
    {
        /** @var KeyCrmCategorySyncService $service */
        $service = Yii::$container->get(KeyCrmCategorySyncService::class);

        $stats = $service->importAllCategories(
            maxPages: $maxPages > 0 ? $maxPages : null,
        );

        $this->stdout("KeyCRM categories import completed.\n");
        $this->stdout("Pages: {$stats['pages']}\n");
        $this->stdout("Processed: {$stats['processed']}\n");
        $this->stdout("Created: {$stats['created']}\n");
        $this->stdout("Updated: {$stats['updated']}\n");
        $this->stdout("Archived: {$stats['archived']}\n");
        $this->stdout("Parents linked: {$stats['parentsLinked']}\n");
        $this->stdout("Missing parents: {$stats['missingParents']}\n");
        $this->stdout("Errors: {$stats['errors']}\n");

        return ExitCode::OK;
    }

    public function actionLinkProductCategories(): int
    {
        /** @var KeyCrmCategorySyncService $service */
        $service = Yii::$container->get(KeyCrmCategorySyncService::class);

        $stats = $service->linkProductsToCategories();

        $this->stdout("Product categories linking completed.\n");
        $this->stdout("Scanned: {$stats['scanned']}\n");
        $this->stdout("Linked: {$stats['linked']}\n");
        $this->stdout("Unchanged: {$stats['unchanged']}\n");
        $this->stdout("Missing category: {$stats['missingCategory']}\n");
        $this->stdout("Empty external category: {$stats['emptyExternalCategory']}\n");
        $this->stdout("Errors: {$stats['errors']}\n");

        return ExitCode::OK;
    }
}