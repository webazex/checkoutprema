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
        $this->stdout('Pages: ' . $stats['pages'] . "\n");
        $this->stdout('Processed: ' . $stats['processed'] . "\n");
        $this->stdout('Created: ' . $stats['created'] . "\n");
        $this->stdout('Updated: ' . $stats['updated'] . "\n");
        $this->stdout('Mappings created: ' . $stats['mapped'] . "\n");

        return ExitCode::OK;
    }
}