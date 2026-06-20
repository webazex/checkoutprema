<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\contracts\delivery\DeliveryDirectorySourceInterface;
use common\contracts\delivery\DeliveryProviderInterface;
use common\dto\delivery\DeliveryAreaSyncDto;
use common\dto\delivery\DeliveryPointSyncDto;
use common\dto\delivery\DeliverySettlementSyncDto;
use common\dto\delivery\DeliverySyncPageDto;
use common\dto\delivery\DeliverySyncStateReadDto;
use common\dto\delivery\DeliveryWriteBatchResultDto;
use common\enums\delivery\DeliveryProviderCapability;
use common\enums\delivery\DeliverySyncScope;
use common\enums\delivery\DeliverySyncStatus;
use common\services\delivery\exceptions\DeliverySyncStateException;
use common\storages\delivery\DeliveryDirectoryWriteStorage;
use common\storages\delivery\DeliverySyncStateStorage;
use InvalidArgumentException;
use LogicException;
use Throwable;
use UnexpectedValueException;
use Yii;

final readonly class DeliverySyncService
{
    public function __construct(
        private DeliveryProviderRegistry $providers,
        private DeliveryDirectoryWriteStorage $writer,
        private DeliverySyncStateStorage $states,
    ) {
    }

    public function sync(
        string $providerCode, DeliverySyncScope $scope, string $scopeExternalRef = '',
        bool $resume = false, int $limit = 500, int $staleAfterSeconds = 900
    ): DeliverySyncStateReadDto {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Delivery sync limit must be greater than zero.'
            );
        }

        $scopeExternalRef = $this->normalizeScopeExternalRef(
            $scope,
            $scopeExternalRef
        );

        $provider = $this->providers->get($providerCode);
        $this->assertProviderSupportsScope($provider, $scope);

        $providerCode = $provider->code();

        /*
         * Текущий DeliverySyncStateStorage принимает bool resume.
         * Здесь определяем, существует ли реально незавершённый progress.
         *
         * Успешный run или failed run без cursor запускаются заново.
         */
        $existingState = $this->states->find(
            $providerCode,
            $scope,
            $scopeExternalRef
        );

        $resumeProgress = $resume
            && $this->isResumable($existingState);

        $state = $this->states->startRun(
            providerCode: $providerCode,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            resume: $resumeProgress,
            staleAfterSeconds: $staleAfterSeconds
        );

        $runToken = $state->runToken;
        $sourceSeenAt = $state->startedAt;

        if ($runToken === null || $sourceSeenAt === null) {
            throw new LogicException(
                'Running delivery sync state must contain run token and started timestamp.'
            );
        }

        Yii::info([
            'event' => 'started',
            'providerCode' => $providerCode,
            'scope' => $scope->value,
            'scopeExternalRef' => $scopeExternalRef,
            'stateId' => $state->id,
            'resumeRequested' => $resume,
            'resumeProgress' => $resumeProgress,
            'cursor' => $state->cursor,
        ], 'delivery.sync');

        try {
            return $this->runPages(
                source: $provider->directorySource(),
                providerCode: $providerCode,
                state: $state,
                runToken: $runToken,
                sourceSeenAt: $sourceSeenAt,
                archivalAllowed: !$resumeProgress,
                limit: $limit
            );
        } catch (Throwable $exception) {
            $this->failRunSafely(
                $state->id,
                $runToken,
                $exception
            );

            Yii::error([
                'event' => 'failed',
                'providerCode' => $providerCode,
                'scope' => $scope->value,
                'scopeExternalRef' => $scopeExternalRef,
                'stateId' => $state->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ], 'delivery.sync');

            throw $exception;
        }
    }

    private function runPages(
        DeliveryDirectorySourceInterface $source, string $providerCode,
        DeliverySyncStateReadDto $state, string $runToken,
        int $sourceSeenAt, bool $archivalAllowed, int $limit
    ): DeliverySyncStateReadDto {
        $cursor = $state->cursor;
        $seenCursors = [];
        $pageNumber = 0;

        while (true) {
            $cursorKey = $this->cursorKey($cursor);

            if (isset($seenCursors[$cursorKey])) {
                throw new UnexpectedValueException(sprintf(
                    'Delivery source returned a cursor cycle at "%s".',
                    $cursor ?? 'null'
                ));
            }

            $seenCursors[$cursorKey] = true;
            $pageNumber++;

            /*
             * Обновляем heartbeat перед потенциально долгим API request.
             */
            $this->states->heartbeat(
                $state->id,
                $runToken
            );

            $page = $this->fetchPage(
                source: $source,
                scope: $state->scope,
                scopeExternalRef: $state->scopeExternalRef,
                cursor: $cursor,
                limit: $limit
            );

            $this->assertPage(
                page: $page,
                scope: $state->scope,
                scopeExternalRef: $state->scopeExternalRef,
                providerCode: $providerCode,
                seenCursors: $seenCursors
            );

            $isFinalPage = $page->nextCursor === null;

            $state = $this->processPage(
                state: $state,
                runToken: $runToken,
                page: $page,
                sourceSeenAt: $sourceSeenAt,
                isFinalPage: $isFinalPage,
                archivalAllowed: $archivalAllowed
            );

            Yii::info([
                'event' => $isFinalPage
                    ? 'completed'
                    : 'page_committed',

                'providerCode' => $state->providerCode,
                'scope' => $state->scope->value,
                'scopeExternalRef' => $state->scopeExternalRef,
                'stateId' => $state->id,
                'pageNumber' => $pageNumber,
                'nextCursor' => $page->nextCursor,
                'processedCount' => $state->processedCount,
                'createdCount' => $state->createdCount,
                'updatedCount' => $state->updatedCount,
                'archivedCount' => $state->archivedCount,
            ], 'delivery.sync');

            if ($isFinalPage) {
                return $state;
            }

            $cursor = $state->cursor;

            if ($cursor === null) {
                throw new LogicException(
                    'Non-final delivery sync page must persist its next cursor.'
                );
            }
        }
    }

    private function processPage(
        DeliverySyncStateReadDto $state, string $runToken,
        DeliverySyncPageDto $page, int $sourceSeenAt,
        bool $isFinalPage, bool $archivalAllowed
    ): DeliverySyncStateReadDto {
        return $this->transactional(function () use (
            $state,
            $runToken,
            $page,
            $sourceSeenAt,
            $isFinalPage,
            $archivalAllowed
        ): DeliverySyncStateReadDto {
            /*
             * Upsert и recordBatch находятся в одной транзакции.
             *
             * Если worker потерял run_token, recordBatch() выбросит
             * ownershipLost, после чего page upsert будет откатан.
             */
            $writeResult = $this->writePage(
                $page,
                $state->scope,
                $sourceSeenAt
            );

            $recordedState = $this->states->recordBatch(
                stateId: $state->id,
                runToken: $runToken,
                nextCursor: $page->nextCursor,
                sourceTotalCount: $page->totalCount,
                result: $writeResult
            );

            if (!$isFinalPage) {
                return $recordedState;
            }

            /*
             * Final page, archival и completeRun находятся
             * в одной транзакции.
             */
            $archivedCount = $archivalAllowed
                ? $this->archiveScope(
                    $recordedState,
                    $sourceSeenAt
                )
                : 0;

            return $this->states->completeRun(
                stateId: $recordedState->id,
                runToken: $runToken,
                archivedCount: $archivedCount
            );
        });
    }

    private function fetchPage(
        DeliveryDirectorySourceInterface $source, DeliverySyncScope $scope,
        string $scopeExternalRef, ?string $cursor, int $limit
    ): DeliverySyncPageDto {
        return match ($scope) {
            DeliverySyncScope::AREAS => $source->fetchAreas(
                cursor: $cursor,
                limit: $limit
            ),

            DeliverySyncScope::SETTLEMENTS => $source->fetchSettlements(
                areaExternalRef: $scopeExternalRef === ''
                    ? null
                    : $scopeExternalRef,

                cursor: $cursor,
                limit: $limit
            ),

            DeliverySyncScope::POINTS => $source->fetchPoints(
                settlementDeliveryRef: $scopeExternalRef === ''
                    ? null
                    : $scopeExternalRef,

                cursor: $cursor,
                limit: $limit
            ),

            DeliverySyncScope::SCHEDULES => throw new LogicException(
                'Schedule sync is performed as part of point sync.'
            ),
        };
    }

    private function writePage(
        DeliverySyncPageDto $page, DeliverySyncScope $scope,
        int $sourceSeenAt
    ): DeliveryWriteBatchResultDto {
        return match ($scope) {
            DeliverySyncScope::AREAS => $this->writer->upsertAreas(
                $page->items,
                $sourceSeenAt
            ),

            DeliverySyncScope::SETTLEMENTS => $this->writer->upsertSettlements(
                $page->items,
                $sourceSeenAt
            ),

            DeliverySyncScope::POINTS => $this->writer->upsertPoints(
                $page->items,
                $sourceSeenAt
            ),

            DeliverySyncScope::SCHEDULES => throw new LogicException(
                'Schedule sync is performed as part of point sync.'
            ),
        };
    }

    private function archiveScope(
        DeliverySyncStateReadDto $state, int $sourceSeenAt
    ): int {
        return match ($state->scope) {
            DeliverySyncScope::AREAS =>
            $this->writer->archiveUnseenAreas(
                $state->providerCode,
                $sourceSeenAt
            ),

            DeliverySyncScope::SETTLEMENTS =>
            $this->writer->archiveUnseenSettlements(
                $state->providerCode,
                $sourceSeenAt,
                $state->scopeExternalRef === ''
                    ? null
                    : $state->scopeExternalRef
            ),

            DeliverySyncScope::POINTS =>
            $this->writer->archiveUnseenPoints(
                $state->providerCode,
                $sourceSeenAt,
                $state->scopeExternalRef === ''
                    ? null
                    : $state->scopeExternalRef
            ),

            DeliverySyncScope::SCHEDULES => throw new LogicException(
                'Schedule sync is performed as part of point sync.'
            ),
        };
    }

    /**
     * @param array<string, true> $seenCursors
     */
    private function assertPage(
        DeliverySyncPageDto $page, DeliverySyncScope $scope,
        string $scopeExternalRef, string $providerCode,
        array $seenCursors
    ): void {
        if (
            $page->nextCursor !== null
            && trim($page->nextCursor) !== $page->nextCursor
        ) {
            throw new UnexpectedValueException(
                'Delivery source cursor must not contain surrounding whitespace.'
            );
        }

        if (
            $page->nextCursor !== null
            && isset(
                $seenCursors[
                $this->cursorKey($page->nextCursor)
                ]
            )
        ) {
            throw new UnexpectedValueException(sprintf(
                'Delivery source returned repeated cursor "%s".',
                $page->nextCursor
            ));
        }

        $expectedClass = match ($scope) {
            DeliverySyncScope::AREAS =>
            DeliveryAreaSyncDto::class,

            DeliverySyncScope::SETTLEMENTS =>
            DeliverySettlementSyncDto::class,

            DeliverySyncScope::POINTS =>
            DeliveryPointSyncDto::class,

            DeliverySyncScope::SCHEDULES => throw new LogicException(
                'Schedule sync is performed as part of point sync.'
            ),
        };

        foreach ($page->items as $item) {
            if (!$item instanceof $expectedClass) {
                throw new UnexpectedValueException(sprintf(
                    'Delivery source returned an invalid item for scope "%s".',
                    $scope->value
                ));
            }

            if ($item->providerCode !== $providerCode) {
                throw new UnexpectedValueException(sprintf(
                    'Delivery source for provider "%s" returned an item for provider "%s".',
                    $providerCode,
                    $item->providerCode
                ));
            }

            if (
                $scope === DeliverySyncScope::SETTLEMENTS
                && $scopeExternalRef !== ''
                && $item->areaExternalRef !== $scopeExternalRef
            ) {
                throw new UnexpectedValueException(sprintf(
                    'Scoped settlement sync for area "%s" returned settlement "%s" from area "%s".',
                    $scopeExternalRef,
                    $item->externalRef,
                    $item->areaExternalRef
                ));
            }

            if (
                $scope === DeliverySyncScope::POINTS
                && $scopeExternalRef !== ''
                && $item->settlementDeliveryRef !== $scopeExternalRef
            ) {
                throw new UnexpectedValueException(sprintf(
                    'Scoped point sync for delivery reference "%s" returned point "%s" with reference "%s".',
                    $scopeExternalRef,
                    $item->externalRef,
                    $item->settlementDeliveryRef ?? 'null'
                ));
            }
        }
    }

    private function isResumable(
        ?DeliverySyncStateReadDto $state
    ): bool {
        if ($state === null) {
            return false;
        }

        if (
            !in_array(
                $state->status,
                [
                    DeliverySyncStatus::RUNNING,
                    DeliverySyncStatus::FAILED,
                ],
                true
            )
        ) {
            return false;
        }

        return $state->startedAt !== null
            && $state->cursor !== null;
    }

    private function assertProviderSupportsScope(
        DeliveryProviderInterface $provider, DeliverySyncScope $scope
    ): void {
        if ($scope === DeliverySyncScope::SCHEDULES) {
            throw new LogicException(
                'Schedule sync is performed as part of point sync.'
            );
        }

        if (
            $scope === DeliverySyncScope::POINTS
            && !in_array(
                DeliveryProviderCapability::PICKUP_POINTS,
                $provider->capabilities(),
                true
            )
        ) {
            throw new LogicException(sprintf(
                'Delivery provider "%s" does not support pickup points.',
                $provider->code()
            ));
        }
    }

    private function normalizeScopeExternalRef(
        DeliverySyncScope $scope, string $scopeExternalRef
    ): string {
        $normalized = trim($scopeExternalRef);

        if ($scopeExternalRef !== '' && $normalized === '') {
            throw new InvalidArgumentException(
                'Delivery sync scope external reference must not contain whitespace only.'
            );
        }

        if (strlen($normalized) > 128) {
            throw new InvalidArgumentException(
                'Delivery sync scope external reference is too long.'
            );
        }

        if (
            $scope === DeliverySyncScope::AREAS
            && $normalized !== ''
        ) {
            throw new InvalidArgumentException(
                'Area sync does not support scopeExternalRef.'
            );
        }

        return $normalized;
    }

    private function failRunSafely(
        int $stateId, string $runToken, Throwable $exception
    ): void {
        try {
            $this->states->failRunFromThrowable(
                stateId: $stateId,
                runToken: $runToken,
                exception: $exception
            );
        } catch (DeliverySyncStateException) {
            /*
             * Ownership уже перехвачен другим worker.
             */
        } catch (Throwable $stateException) {
            Yii::error([
                'event' => 'state_failure_not_recorded',
                'stateId' => $stateId,
                'originalException' => $exception::class,
                'stateException' => $stateException::class,
                'stateExceptionMessage'
                => $stateException->getMessage(),
            ], 'delivery.sync');
        }
    }

    private function cursorKey(?string $cursor): string
    {
        return $cursor === null
            ? '__initial__'
            : 'cursor:' . $cursor;
    }

    private function transactional(callable $callback): mixed
    {
        $db = Yii::$app->db;
        $activeTransaction = $db->getTransaction();

        if (
            $activeTransaction !== null
            && $activeTransaction->getIsActive()
        ) {
            return $callback();
        }

        $transaction = $db->beginTransaction();

        try {
            $result = $callback();
            $transaction->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($transaction->getIsActive()) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }
}