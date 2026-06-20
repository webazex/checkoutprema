<?php

declare(strict_types=1);

namespace console\controllers;

use common\dto\delivery\DeliverySyncStateReadDto;
use common\jobs\delivery\DeliverySyncJob;
use common\services\delivery\DeliverySyncSchedulerService;
use common\services\delivery\DeliverySyncStatusService;
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
            return array_merge(
                $options,
                ['resume', 'force', 'limit', 'staleAfterSeconds']
            );
        }

        if ($actionID === 'sync-status') {
            return array_merge($options, ['staleAfterSeconds']);
        }

        return $options;
    }

    public function actionSync(
        string $providerCode,
        string $scope = DeliverySyncJob::SCOPE_ALL,
        string $scopeExternalRef = ''
    ): int {
        /** @var DeliverySyncSchedulerService $scheduler */
        $scheduler = Yii::$container->get(
            DeliverySyncSchedulerService::class
        );

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
            $this->stdout(
                "Delivery sync job skipped: duplicate active job exists.\n"
            );

            return ExitCode::OK;
        }

        $this->stdout("Delivery sync job pushed. Job ID: {$jobId}\n");

        return ExitCode::OK;
    }

    public function actionSyncStatus(string $providerCode): int
    {
        /** @var DeliverySyncStatusService $service */
        $service = Yii::$container->get(
            DeliverySyncStatusService::class
        );

        $snapshot = $service->snapshot(
            $providerCode,
            $this->staleAfterSeconds
        );

        $this->stdout(sprintf(
            "Provider: %s (%s)\n",
            $snapshot['providerName'],
            $snapshot['providerCode']
        ));

        $this->stdout(sprintf(
            "Health: %s\n",
            $snapshot['healthy'] ? 'OK' : 'ERROR'
        ));

        $this->stdout(sprintf(
            "Generated: %s\n\n",
            $this->formatTimestamp($snapshot['generatedAt'])
        ));

        foreach ($snapshot['scopes'] as $scope => $scopeStatus) {
            $this->writeScopeStatus(
                $scope,
                $scopeStatus['state'],
                $scopeStatus['stale'],
                $scopeStatus['heartbeatAge']
            );
        }

        $queue = $snapshot['queue'];

        $this->stdout("Queue\n");
        $this->stdout("  waiting: {$queue['waiting']}\n");
        $this->stdout("  delayed: {$queue['delayed']}\n");
        $this->stdout("  reserved: {$queue['reserved']}\n");
        $this->stdout("  done: {$queue['done']}\n");

        return $snapshot['healthy']
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }

    private function writeScopeStatus(
        string $scope,
        ?DeliverySyncStateReadDto $state,
        bool $stale,
        ?int $heartbeatAge
    ): void {
        $this->stdout($scope . "\n");

        if ($state === null) {
            $this->stdout("  status: not_initialized\n\n");

            return;
        }

        $this->stdout("  status: {$state->status->value}\n");
        $this->stdout('  stale: ' . ($stale ? 'yes' : 'no') . "\n");
        $this->stdout("  cursor: " . ($state->cursor ?? 'null') . "\n");

        if ($heartbeatAge !== null) {
            $this->stdout("  heartbeat_age: {$heartbeatAge} sec\n");
        }

        $this->stdout(
            "  started_at: {$this->formatTimestamp($state->startedAt)}\n"
        );

        $this->stdout(
            "  finished_at: {$this->formatTimestamp($state->finishedAt)}\n"
        );

        $this->stdout(
            "  last_success_at: {$this->formatTimestamp($state->lastSuccessAt)}\n"
        );

        $this->stdout("  source_total: " . ($state->sourceTotalCount ?? 'null') . "\n");
        $this->stdout("  processed: {$state->processedCount}\n");
        $this->stdout("  created: {$state->createdCount}\n");
        $this->stdout("  updated: {$state->updatedCount}\n");
        $this->stdout("  archived: {$state->archivedCount}\n");

        if ($state->lastErrorType !== null) {
            $this->stdout("  error_type: {$state->lastErrorType}\n");
        }

        if ($state->lastErrorCode !== null) {
            $this->stdout("  error_code: {$state->lastErrorCode}\n");
        }

        if ($state->lastErrorMessage !== null) {
            $this->stdout("  error: {$state->lastErrorMessage}\n");
        }

        $this->stdout("\n");
    }

    private function formatTimestamp(?int $timestamp): string
    {
        if ($timestamp === null) {
            return 'null';
        }

        return Yii::$app->formatter->asDatetime(
            $timestamp,
            'php:Y-m-d H:i:s'
        );
    }
}