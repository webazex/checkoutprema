<?php

declare(strict_types=1);

namespace console\controllers;

use common\jobs\delivery\DeliverySyncJob;
use common\services\delivery\DeliverySyncSchedulerService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class DeliveryController extends Controller
{
    public int $resume = 0;
    public int $force = 0;
    public int $limit = 500;
    public int $staleAfterSeconds = 900;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'sync') {
            $options = array_merge($options, ['resume', 'force', 'limit', 'staleAfterSeconds']);
        }

        return $options;
    }

    public function actionSync(string $providerCode, string $scope = DeliverySyncJob::SCOPE_ALL, string $scopeExternalRef = ''): int
    {
        /** @var DeliverySyncSchedulerService $scheduler */
        $scheduler = Yii::$container->get(DeliverySyncSchedulerService::class);

        $jobId = $scheduler->schedule(
            providerCode: $providerCode,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            resume: (bool)$this->resume,
            force: (bool)$this->force,
            limit: $this->limit,
            staleAfterSeconds: $this->staleAfterSeconds
        );

        if ($jobId === null) {
            $this->stdout("Delivery sync job skipped: duplicate active job exists.\n");

            return ExitCode::OK;
        }

        $this->stdout("Delivery sync job pushed. Job ID: {$jobId}\n");

        return ExitCode::OK;
    }
}