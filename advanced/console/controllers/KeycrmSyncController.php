<?php

declare(strict_types=1);

namespace console\controllers;

use common\services\keycrm\KeyCrmSyncSchedulerService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class KeycrmSyncController extends Controller
{
    public function actionProducts(int $maxPages = 0): int
    {
        /** @var KeyCrmSyncSchedulerService $scheduler */
        $scheduler = Yii::$container->get(KeyCrmSyncSchedulerService::class);

        $jobId = $scheduler->scheduleProducts(
            maxPages: $maxPages,
            withCustomFields: true,
        );

        $this->stdout($this->formatJobResult('KeyCRM products sync', $jobId));

        return ExitCode::OK;
    }

    private function formatJobResult(string $label, int|string|null $jobId): string
    {
        if ($jobId === null) {
            return "{$label} job skipped: duplicate active job exists.\n";
        }

        return "{$label} job pushed. Job ID: {$jobId}\n";
    }

    public function actionCategories(int $maxPages = 0, int $linkProducts = 1): int
    {
        /** @var KeyCrmSyncSchedulerService $scheduler */
        $scheduler = Yii::$container->get(KeyCrmSyncSchedulerService::class);

        $jobId = $scheduler->scheduleCategories(
            maxPages: $maxPages,
            linkProducts: (bool)$linkProducts,
        );

        $this->stdout($this->formatJobResult('KeyCRM categories sync', $jobId));

        return ExitCode::OK;
    }

    public function actionFull(int $maxPages = 0): int
    {
        /** @var KeyCrmSyncSchedulerService $scheduler */
        $scheduler = Yii::$container->get(KeyCrmSyncSchedulerService::class);

        $result = $scheduler->scheduleFull($maxPages);

        $this->stdout($this->formatJobResult('KeyCRM categories sync', $result['categories']));
        $this->stdout($this->formatJobResult('KeyCRM products sync', $result['products']));

        return ExitCode::OK;
    }
}