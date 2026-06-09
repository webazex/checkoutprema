<?php

declare(strict_types=1);

namespace console\controllers;

use common\jobs\keycrm\KeyCrmImportCategoriesJob;
use common\jobs\keycrm\KeyCrmImportProductsJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class KeycrmSyncController extends Controller
{
    public function actionProducts(int $maxPages = 0): int
    {
        $jobId = Yii::$app->queue->push(new KeyCrmImportProductsJob([
            'maxPages' => $maxPages,
            'withCustomFields' => true,
        ]));

        $this->stdout("KeyCRM products sync job pushed. Job ID: {$jobId}\n");

        return ExitCode::OK;
    }

    public function actionCategories(int $maxPages = 0, int $linkProducts = 1): int
    {
        $jobId = Yii::$app->queue->push(new KeyCrmImportCategoriesJob([
            'maxPages' => $maxPages,
            'linkProducts' => (bool)$linkProducts,
        ]));

        $this->stdout("KeyCRM categories sync job pushed. Job ID: {$jobId}\n");

        return ExitCode::OK;
    }

    public function actionFull(int $maxPages = 0): int
    {
        $categoriesJobId = Yii::$app->queue->push(new KeyCrmImportCategoriesJob([
            'maxPages' => $maxPages,
            'linkProducts' => true,
        ]));

        $productsJobId = Yii::$app->queue->push(new KeyCrmImportProductsJob([
            'maxPages' => $maxPages,
            'withCustomFields' => true,
        ]));

        $this->stdout("KeyCRM categories sync job pushed. Job ID: {$categoriesJobId}\n");
        $this->stdout("KeyCRM products sync job pushed. Job ID: {$productsJobId}\n");

        return ExitCode::OK;
    }
}