<?php

declare(strict_types=1);

namespace console\controllers;

use common\dto\delivery\DeliveryPointReadDto;
use common\dto\delivery\DeliveryProviderReadDto;
use common\dto\delivery\DeliverySyncStateReadDto;
use common\jobs\delivery\DeliverySyncJob;
use common\models\delivery\DeliveryPointModel;
use common\services\delivery\DeliveryDirectoryCacheService;
use common\services\delivery\DeliveryDirectoryReadService;
use common\services\delivery\DeliverySyncSchedulerService;
use common\services\delivery\DeliverySyncStatusService;
use common\storages\delivery\DeliveryPointStorage;
use common\dto\delivery\DeliveryPointSearchRequestDto;
use common\services\delivery\DeliveryPointSearchService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class DeliveryController extends Controller
{
    public int $resume = 0;
    public int $force = 0;
    public int $limit = 500;
    public int $staleAfterSeconds = 900;
    public int $resetCache = 1;
    public int $searchLimit = 30;

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

        if ($actionID === 'read-test') {
            return array_merge($options, ['resetCache']);
        }

        if ($actionID === 'search-test') {
            return array_merge($options, ['searchLimit']);
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

    public function actionReadTest(string $providerCode): int
    {
        $providerCode = strtolower(trim($providerCode));

        /** @var DeliveryDirectoryReadService $reader */
        $reader = Yii::$container->get(
            DeliveryDirectoryReadService::class
        );

        /** @var DeliveryDirectoryCacheService $cache */
        $cache = Yii::$container->get(
            DeliveryDirectoryCacheService::class
        );

        $this->stdout("Delivery directory read/cache test\n");
        $this->stdout("Provider code: {$providerCode}\n");
        $this->stdout(
            'Cache reset: ' . ((bool)$this->resetCache ? 'yes' : 'no') . "\n\n"
        );

        if ((bool)$this->resetCache) {
            $cache->invalidateProviders();
        }

        $healthy = true;

        [$providersFirst, $providersFirstTime] = $this->measureRead(
            fn(): array => $reader->providers()
        );

        [$providersSecond, $providersSecondTime] = $this->measureRead(
            fn(): array => $reader->providers()
        );

        $healthy = $this->writeReadResult(
                label: 'providers',
                firstItems: $providersFirst,
                firstTime: $providersFirstTime,
                secondItems: $providersSecond,
                secondTime: $providersSecondTime
            ) && $healthy;

        $provider = $this->findProvider(
            $providersFirst,
            $providerCode
        );

        if ($provider === null) {
            $this->stdout(
                "\nResult: ERROR — provider was not returned by read layer.\n"
            );

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $selectedPoint = $this->selectSamplePoint($provider);

        if ($selectedPoint === null) {
            $this->stdout(
                "\nResult: ERROR — provider has no selectable delivery points.\n"
            );

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(sprintf(
            "Selected provider: #%d %s (%s)\n",
            $provider->id,
            $provider->name,
            $provider->code
        ));

        $this->stdout(sprintf(
            "Sample point: #%d, number=%s, settlement=%s, address=%s\n\n",
            $selectedPoint->id,
            $selectedPoint->number ?? 'null',
            $selectedPoint->settlementName,
            $selectedPoint->address
        ));

        [$areasFirst, $areasFirstTime] = $this->measureRead(
            fn(): array => $reader->areas($providerCode)
        );

        [$areasSecond, $areasSecondTime] = $this->measureRead(
            fn(): array => $reader->areas($providerCode)
        );

        $healthy = $this->writeReadResult(
                label: 'areas',
                firstItems: $areasFirst,
                firstTime: $areasFirstTime,
                secondItems: $areasSecond,
                secondTime: $areasSecondTime
            ) && $healthy;

        $selectedArea = $this->findById(
            $areasFirst,
            $selectedPoint->areaId
        );

        if ($selectedArea === null) {
            $this->stdout(
                "  selected area presence: no\n\n"
            );

            $healthy = false;
        } else {
            $this->stdout(sprintf(
                "Selected area: #%d %s\n\n",
                $selectedPoint->areaId,
                $selectedPoint->areaName
            ));
        }

        [$settlementsFirst, $settlementsFirstTime] = $this->measureRead(
            fn(): array => $reader->settlements(
                $providerCode,
                $selectedPoint->areaId
            )
        );

        [$settlementsSecond, $settlementsSecondTime] = $this->measureRead(
            fn(): array => $reader->settlements(
                $providerCode,
                $selectedPoint->areaId
            )
        );

        $healthy = $this->writeReadResult(
                label: 'settlements',
                firstItems: $settlementsFirst,
                firstTime: $settlementsFirstTime,
                secondItems: $settlementsSecond,
                secondTime: $settlementsSecondTime
            ) && $healthy;

        $selectedSettlement = $this->findById(
            $settlementsFirst,
            $selectedPoint->settlementId
        );

        if ($selectedSettlement === null) {
            $this->stdout(
                "  selected settlement presence: no\n\n"
            );

            $healthy = false;
        } else {
            $this->stdout(sprintf(
                "Selected settlement: #%d %s\n\n",
                $selectedPoint->settlementId,
                $selectedPoint->settlementName
            ));
        }

        [$pointsFirst, $pointsFirstTime] = $this->measureRead(
            fn(): array => $reader->points(
                $providerCode,
                $selectedPoint->settlementId
            )
        );

        [$pointsSecond, $pointsSecondTime] = $this->measureRead(
            fn(): array => $reader->points(
                $providerCode,
                $selectedPoint->settlementId
            )
        );

        $healthy = $this->writeReadResult(
                label: 'points',
                firstItems: $pointsFirst,
                firstTime: $pointsFirstTime,
                secondItems: $pointsSecond,
                secondTime: $pointsSecondTime
            ) && $healthy;

        $selectedPointFromRead = $this->findById(
            $pointsFirst,
            $selectedPoint->id
        );

        if (!$selectedPointFromRead instanceof DeliveryPointReadDto) {
            $this->stdout(
                "  selected point presence: no\n\n"
            );

            $healthy = false;
        } else {
            $this->stdout(sprintf(
                "Selected point from read layer: #%d, number=%s, type=%s\n\n",
                $selectedPointFromRead->id,
                $selectedPointFromRead->number ?? 'null',
                $selectedPointFromRead->typeCode
            ));
        }

        [$schedulesFirst, $schedulesFirstTime] = $this->measureRead(
            fn(): array => $reader->pointSchedules(
                $providerCode,
                $selectedPoint->id
            )
        );

        [$schedulesSecond, $schedulesSecondTime] = $this->measureRead(
            fn(): array => $reader->pointSchedules(
                $providerCode,
                $selectedPoint->id
            )
        );

        $healthy = $this->writeReadResult(
                label: 'point schedules',
                firstItems: $schedulesFirst,
                firstTime: $schedulesFirstTime,
                secondItems: $schedulesSecond,
                secondTime: $schedulesSecondTime
            ) && $healthy;

        $this->writeSchedulePreview($schedulesFirst);

        $this->stdout(
            "\nResult: " . ($healthy ? 'OK' : 'ERROR') . "\n"
        );

        return $healthy
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionSearchTest(
        string $providerCode,
        string $query
    ): int {
        $providerCode = strtolower(trim($providerCode));

        /** @var DeliveryPointSearchService $service */
        $service = Yii::$container->get(
            DeliveryPointSearchService::class
        );

        $this->stdout("Delivery point search test\n");
        $this->stdout("Provider code: {$providerCode}\n");
        $this->stdout("Query: {$query}\n");
        $this->stdout("Limit: {$this->searchLimit}\n\n");

        $globalResults = $this->runSearchCase(
            service: $service,
            label: 'global DB search',
            request: new DeliveryPointSearchRequestDto(
                providerCode: $providerCode,
                query: $query,
                limit: $this->searchLimit
            )
        );

        if ($globalResults === []) {
            $this->stdout(
                "\nResult: ERROR — global search returned no results.\n"
            );

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $samplePoint = $globalResults[0]->point;

        $areaResults = $this->runSearchCase(
            service: $service,
            label: 'area DB search',
            request: new DeliveryPointSearchRequestDto(
                providerCode: $providerCode,
                query: $query,
                areaRef: $samplePoint->areaExternalRef,
                limit: $this->searchLimit
            )
        );

        $cityResults = $this->runSearchCase(
            service: $service,
            label: 'city cache search',
            request: new DeliveryPointSearchRequestDto(
                providerCode: $providerCode,
                query: $query,
                areaRef: $samplePoint->areaExternalRef,
                cityRef: $samplePoint->settlementDeliveryRef,
                settlementRef: $samplePoint->settlementExternalRef,
                limit: $this->searchLimit
            )
        );

        $healthy = $areaResults !== [] && $cityResults !== [];

        $this->stdout(
            "\nResult: " . ($healthy ? 'OK' : 'ERROR') . "\n"
        );

        return $healthy
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * @param callable(): array $callback
     *
     * @return array{0: array, 1: float}
     */
    private function measureRead(callable $callback): array
    {
        $startedAt = hrtime(true);
        $items = $callback();
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        return [
            $items,
            $elapsedMilliseconds,
        ];
    }

    /**
     * @param array<mixed> $firstItems
     * @param array<mixed> $secondItems
     */
    private function writeReadResult(
        string $label,
        array $firstItems,
        float $firstTime,
        array $secondItems,
        float $secondTime
    ): bool {
        $sameCount = count($firstItems) === count($secondItems);
        $sameData = $firstItems == $secondItems;
        $valid = $sameCount && $sameData;

        $this->stdout($label . "\n");

        $this->stdout(sprintf(
            "  first: count=%d time=%.3f ms\n",
            count($firstItems),
            $firstTime
        ));

        $this->stdout(sprintf(
            "  second: count=%d time=%.3f ms\n",
            count($secondItems),
            $secondTime
        ));

        $this->stdout(
            '  identical: ' . ($valid ? 'yes' : 'no') . "\n\n"
        );

        return $valid;
    }

    /**
     * @param list<DeliveryProviderReadDto> $providers
     */
    private function findProvider(
        array $providers,
        string $providerCode
    ): ?DeliveryProviderReadDto {
        foreach ($providers as $provider) {
            if ($provider->code === $providerCode) {
                return $provider;
            }
        }

        return null;
    }

    private function selectSamplePoint(
        DeliveryProviderReadDto $provider
    ): ?DeliveryPointReadDto {
        $pointId = DeliveryPointModel::find()
            ->select('id')
            ->byProviderId($provider->id)
            ->availableForCheckout()
            ->orderBy(['id' => SORT_ASC])
            ->scalar();

        if ($pointId === false || $pointId === null) {
            return null;
        }

        /** @var DeliveryPointStorage $points */
        $points = Yii::$container->get(
            DeliveryPointStorage::class
        );

        return $points->findSelectableById((int)$pointId);
    }

    /**
     * @param array<object> $items
     */
    private function findById(array $items, int $id): ?object
    {
        foreach ($items as $item) {
            if (!property_exists($item, 'id')) {
                continue;
            }

            if ((int)$item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<object> $schedules
     */
    private function writeSchedulePreview(array $schedules): void
    {
        if ($schedules === []) {
            $this->stdout("Schedule preview: empty\n");

            return;
        }

        $this->stdout("Schedule preview:\n");

        foreach ($schedules as $schedule) {
            if (!property_exists($schedule, 'weekday')) {
                continue;
            }

            $interval = $schedule->isClosed
                ? 'closed'
                : sprintf(
                    '%s–%s',
                    $schedule->opensAt ?? 'null',
                    $schedule->closesAt ?? 'null'
                );

            $this->stdout(sprintf(
                "  weekday=%d interval=%d %s\n",
                $schedule->weekday,
                $schedule->intervalNo,
                $interval
            ));
        }
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

    /**
     * @return list<\common\dto\delivery\DeliveryPointSearchResultDto>
     */
    private function runSearchCase(
        DeliveryPointSearchService $service,
        string $label,
        DeliveryPointSearchRequestDto $request
    ): array {
        $startedAt = hrtime(true);
        $results = $service->search($request);
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        $this->stdout($label . "\n");

        $this->stdout(sprintf(
            "  count: %d\n",
            count($results)
        ));

        $this->stdout(sprintf(
            "  time: %.3f ms\n",
            $elapsedMilliseconds
        ));

        foreach (array_slice($results, 0, 5) as $index => $result) {
            $point = $result->point;

            $this->stdout(sprintf(
                "  #%d pointId=%d number=%s type=%s settlement=%s area=%s label=%s\n",
                $index + 1,
                $point->id,
                $point->number ?? 'null',
                $point->typeCode,
                $point->settlementName,
                $point->areaName,
                $result->label
            ));
        }

        $this->stdout("\n");

        return $results;
    }
}