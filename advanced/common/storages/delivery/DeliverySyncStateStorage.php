<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliverySyncStateReadDto;
use common\dto\delivery\DeliveryWriteBatchResultDto;
use common\enums\delivery\DeliverySyncScope;
use common\enums\delivery\DeliverySyncStatus;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliverySyncStateModel;
use common\services\delivery\exceptions\DeliverySyncStateException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use yii\db\Expression;
use yii\db\IntegrityException;

final readonly class DeliverySyncStateStorage
{
    private const RUN_TOKEN_BYTES = 32;
    private const RUN_TOKEN_LENGTH = 64;

    private const ERROR_TYPE_MAX_LENGTH = 64;
    private const ERROR_CODE_MAX_LENGTH = 128;
    private const ERROR_MESSAGE_MAX_LENGTH = 16000;

    public function __construct(private DeliveryProviderStorage $providers, private DeliveryReadMapper $mapper)
    {
    }

    public function find(
        string $providerCode,
        DeliverySyncScope $scope,
        string $scopeExternalRef = ''
    ): ?DeliverySyncStateReadDto {
        $scopeExternalRef = $this->normalizeScopeExternalRef($scopeExternalRef);
        $provider = $this->providers->getByCode($providerCode);

        $model = $this->findModel(
            providerId: $provider->id,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            withProvider: true
        );

        return $model === null
            ? null
            : $this->mapper->mapSyncState($model);
    }

    public function findById(int $stateId): ?DeliverySyncStateReadDto
    {
        $this->assertStateId($stateId);

        $model = DeliverySyncStateModel::find()
            ->byId($stateId)
            ->with('provider')
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapSyncState($model);
    }

    public function getOrCreate(
        string $providerCode,
        DeliverySyncScope $scope,
        string $scopeExternalRef = ''
    ): DeliverySyncStateReadDto {
        $scopeExternalRef = $this->normalizeScopeExternalRef($scopeExternalRef);
        $provider = $this->providers->getByCode($providerCode);

        $model = $this->findModel(
            providerId: $provider->id,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            withProvider: true
        );

        if ($model !== null) {
            return $this->mapper->mapSyncState($model);
        }

        $model = new DeliverySyncStateModel([
            'provider_id' => $provider->id,
            'scope' => $scope->value,
            'scope_external_ref' => $scopeExternalRef,
        ]);

        try {
            $this->saveModel($model);
        } catch (IntegrityException|RuntimeException $exception) {
            $model = $this->findModel(
                providerId: $provider->id,
                scope: $scope,
                scopeExternalRef: $scopeExternalRef,
                withProvider: true
            );

            if ($model === null) {
                throw $exception;
            }

            return $this->mapper->mapSyncState($model);
        }

        return $this->reload((int)$model->id);
    }

    public function startRun(
        string $providerCode,
        DeliverySyncScope $scope,
        string $scopeExternalRef = '',
        bool $resume = false,
        int $staleAfterSeconds = 900
    ): DeliverySyncStateReadDto {
        if ($staleAfterSeconds < 1) {
            throw new InvalidArgumentException(
                'Delivery sync stale timeout must be greater than zero.'
            );
        }

        $state = $this->getOrCreate(
            providerCode: $providerCode,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef
        );

        $now = time();
        $staleBefore = max(0, $now - $staleAfterSeconds);
        $runToken = bin2hex(random_bytes(self::RUN_TOKEN_BYTES));

        $attributes = [
            'status' => DeliverySyncStatus::RUNNING->value,
            'run_token' => $runToken,
            'started_at' => $resume && $state->startedAt !== null
                ? $state->startedAt
                : $now,
            'heartbeat_at' => $now,
            'finished_at' => null,
            'last_error_at' => null,
            'last_error_type' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'updated_at' => $now,
        ];

        if (!$resume) {
            $attributes = array_merge($attributes, [
                'cursor' => null,
                'source_total_count' => null,
                'processed_count' => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'archived_count' => 0,
            ]);
        }

        $affectedRows = DeliverySyncStateModel::updateAll(
            $attributes,
            [
                'and',
                ['id' => $state->id],
                [
                    'or',
                    ['<>', 'status', DeliverySyncStatus::RUNNING->value],
                    ['heartbeat_at' => null],
                    ['<', 'heartbeat_at', $staleBefore],
                ],
            ]
        );

        if ($affectedRows !== 1) {
            throw DeliverySyncStateException::alreadyRunning($state->id);
        }

        return $this->reload($state->id);
    }

    public function heartbeat(int $stateId, string $runToken): DeliverySyncStateReadDto
    {
        $runToken = $this->normalizeRunToken($runToken);
        $now = time();

        $this->updateOwnedRunningState(
            stateId: $stateId,
            runToken: $runToken,
            attributes: [
                'heartbeat_at' => $now,
                'updated_at' => $now,
            ]
        );

        return $this->reload($stateId);
    }

    public function recordBatch(
        int $stateId,
        string $runToken,
        ?string $nextCursor,
        ?int $sourceTotalCount,
        DeliveryWriteBatchResultDto $result
    ): DeliverySyncStateReadDto {
        $runToken = $this->normalizeRunToken($runToken);
        $nextCursor = $this->normalizeCursor($nextCursor);

        if ($sourceTotalCount !== null && $sourceTotalCount < 0) {
            throw new InvalidArgumentException(
                'Delivery source total count must not be negative.'
            );
        }

        $now = time();

        $attributes = [
            'cursor' => $nextCursor,
            'heartbeat_at' => $now,
            'updated_at' => $now,

            'processed_count' => new Expression(
                '[[processed_count]] + :sync_processed',
                [':sync_processed' => $result->processedCount]
            ),

            'created_count' => new Expression(
                '[[created_count]] + :sync_created',
                [':sync_created' => $result->createdCount]
            ),

            'updated_count' => new Expression(
                '[[updated_count]] + :sync_updated',
                [':sync_updated' => $result->updatedCount]
            ),

            'archived_count' => new Expression(
                '[[archived_count]] + :sync_archived',
                [':sync_archived' => $result->archivedCount]
            ),
        ];

        if ($sourceTotalCount !== null) {
            $attributes['source_total_count'] = $sourceTotalCount;
        }

        $this->updateOwnedRunningState(
            stateId: $stateId,
            runToken: $runToken,
            attributes: $attributes
        );

        return $this->reload($stateId);
    }

    public function completeRun(
        int $stateId,
        string $runToken,
        int $archivedCount = 0
    ): DeliverySyncStateReadDto {
        $runToken = $this->normalizeRunToken($runToken);

        if ($archivedCount < 0) {
            throw new InvalidArgumentException(
                'Delivery archived count must not be negative.'
            );
        }

        $now = time();

        $this->updateOwnedRunningState(
            stateId: $stateId,
            runToken: $runToken,
            attributes: [
                'status' => DeliverySyncStatus::SUCCEEDED->value,
                'run_token' => null,
                'cursor' => null,
                'heartbeat_at' => $now,
                'finished_at' => $now,
                'last_success_at' => $now,
                'last_error_at' => null,
                'last_error_type' => null,
                'last_error_code' => null,
                'last_error_message' => null,

                'archived_count' => new Expression(
                    '[[archived_count]] + :complete_archived',
                    [':complete_archived' => $archivedCount]
                ),

                'updated_at' => $now,
            ]
        );

        return $this->reload($stateId);
    }

    public function failRun(
        int $stateId,
        string $runToken,
        string $errorType,
        ?string $errorCode,
        string $errorMessage
    ): DeliverySyncStateReadDto {
        $runToken = $this->normalizeRunToken($runToken);

        $errorType = $this->normalizeErrorField(
            value: $errorType,
            maxLength: self::ERROR_TYPE_MAX_LENGTH,
            field: 'errorType',
            required: true
        );

        $errorCode = $this->normalizeErrorField(
            value: $errorCode,
            maxLength: self::ERROR_CODE_MAX_LENGTH,
            field: 'errorCode',
            required: false
        );

        $errorMessage = $this->normalizeErrorField(
            value: $errorMessage,
            maxLength: self::ERROR_MESSAGE_MAX_LENGTH,
            field: 'errorMessage',
            required: true
        );

        $now = time();

        $this->updateOwnedRunningState(
            stateId: $stateId,
            runToken: $runToken,
            attributes: [
                'status' => DeliverySyncStatus::FAILED->value,
                'run_token' => null,
                'heartbeat_at' => $now,
                'finished_at' => $now,
                'last_error_at' => $now,
                'last_error_type' => $errorType,
                'last_error_code' => $errorCode,
                'last_error_message' => $errorMessage,
                'updated_at' => $now,
            ]
        );

        return $this->reload($stateId);
    }

    public function failRunFromThrowable(
        int $stateId,
        string $runToken,
        Throwable $exception
    ): DeliverySyncStateReadDto {
        $errorCode = $exception->getCode() === 0
            ? null
            : (string)$exception->getCode();

        $errorMessage = trim($exception->getMessage());

        if ($errorMessage === '') {
            $errorMessage = $exception::class;
        }

        return $this->failRun(
            stateId: $stateId,
            runToken: $runToken,
            errorType: $exception::class,
            errorCode: $errorCode,
            errorMessage: $errorMessage
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function updateOwnedRunningState(int $stateId, string $runToken, array $attributes): void
    {
        $this->assertStateId($stateId);

        $condition = [
            'id' => $stateId,
            'status' => DeliverySyncStatus::RUNNING->value,
            'run_token' => $runToken,
        ];

        $affectedRows = DeliverySyncStateModel::updateAll(
            $attributes,
            $condition
        );

        if ($affectedRows === 1) {
            return;
        }

        /*
         * MySQL возвращает 0, если UPDATE фактически не изменил значения.
         * Например, два heartbeat выполнены в одну секунду.
         */
        $stillOwned = DeliverySyncStateModel::find()
            ->where($condition)
            ->exists();

        if ($stillOwned) {
            return;
        }

        throw DeliverySyncStateException::ownershipLost($stateId);
    }

    private function reload(int $stateId): DeliverySyncStateReadDto
    {
        $this->assertStateId($stateId);

        $model = DeliverySyncStateModel::find()
            ->byId($stateId)
            ->with('provider')
            ->one();

        if ($model === null) {
            throw new RuntimeException(sprintf(
                'Delivery sync state #%d was not found.',
                $stateId
            ));
        }

        return $this->mapper->mapSyncState($model);
    }

    private function findModel(
        int $providerId,
        DeliverySyncScope $scope,
        string $scopeExternalRef,
        bool $withProvider
    ): ?DeliverySyncStateModel {
        $query = DeliverySyncStateModel::find()
            ->byProviderScope(
                $providerId,
                $scope,
                $scopeExternalRef
            );

        if ($withProvider) {
            $query->with('provider');
        }

        return $query->one();
    }

    private function saveModel(DeliverySyncStateModel $model): void
    {
        if ($model->save()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Unable to save delivery sync state: %s',
            $this->formatErrors($model)
        ));
    }

    private function assertStateId(int $stateId): void
    {
        if ($stateId < 1) {
            throw new InvalidArgumentException(
                'Delivery sync state ID must be greater than zero.'
            );
        }
    }

    private function normalizeRunToken(string $runToken): string
    {
        $runToken = trim($runToken);

        if (
            strlen($runToken) !== self::RUN_TOKEN_LENGTH
            || preg_match('/^[a-f0-9]{64}$/', $runToken) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid delivery sync run token.'
            );
        }

        return $runToken;
    }

    private function normalizeScopeExternalRef(string $scopeExternalRef): string
    {
        $scopeExternalRef = trim($scopeExternalRef);

        if (strlen($scopeExternalRef) > 128) {
            throw new InvalidArgumentException(
                'Delivery sync scope external reference is too long.'
            );
        }

        return $scopeExternalRef;
    }

    private function normalizeCursor(?string $cursor): ?string
    {
        if ($cursor === null) {
            return null;
        }

        $cursor = trim($cursor);

        if ($cursor === '') {
            throw new InvalidArgumentException(
                'Delivery sync cursor must be null or non-empty.'
            );
        }

        return $cursor;
    }

    private function normalizeErrorField(
        ?string $value,
        int $maxLength,
        string $field,
        bool $required
    ): ?string {
        if ($value === null) {
            if ($required) {
                throw new InvalidArgumentException(sprintf(
                    'Delivery sync %s must not be null.',
                    $field
                ));
            }

            return null;
        }

        $value = trim($value);

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException(sprintf(
                    'Delivery sync %s must not be empty.',
                    $field
                ));
            }

            return null;
        }

        return mb_substr(
            $value,
            0,
            $maxLength,
            'UTF-8'
        );
    }

    private function formatErrors(DeliverySyncStateModel $model): string
    {
        $encoded = json_encode(
            $model->getErrors(),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        return $encoded === false
            ? 'unknown validation error'
            : $encoded;
    }
}