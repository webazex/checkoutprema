<?php

declare(strict_types=1);

namespace console\controllers;

use common\services\keycrm\KeyCrmProductImportService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

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
}