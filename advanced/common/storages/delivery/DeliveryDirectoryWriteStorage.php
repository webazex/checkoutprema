<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliveryAreaSyncDto;
use common\dto\delivery\DeliverySettlementSyncDto;
use common\dto\delivery\DeliveryWriteBatchResultDto;
use common\models\delivery\DeliveryAreaModel;
use common\models\delivery\DeliverySettlementModel;
use common\dto\delivery\DeliveryPointSyncDto;
use common\models\delivery\DeliveryPointModel;
use common\models\delivery\DeliveryPointScheduleModel;
use UnexpectedValueException;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;
use Yii;

final readonly class DeliveryDirectoryWriteStorage
{
    public function __construct(
        private DeliveryProviderStorage $providers,
        private DeliveryPointTypeStorage $pointTypes,
    ) {
    }

    /**
     * @param list<DeliveryAreaSyncDto> $items
     */
    public function upsertAreas(array $items, int $sourceSeenAt): DeliveryWriteBatchResultDto {
        $this->assertSourceSeenAt($sourceSeenAt);

        if ($items === []) {
            return DeliveryWriteBatchResultDto::empty();
        }

        [$providerCode, $externalRefs] = $this->validateAreaBatch($items);

        return $this->transactional(function () use (
            $items,
            $providerCode,
            $externalRefs,
            $sourceSeenAt
        ): DeliveryWriteBatchResultDto {
            $providerId = $this->providers
                ->getByCode($providerCode)
                ->id;

            $existingRefs = DeliveryAreaModel::find()
                ->select('external_ref')
                ->where([
                    'provider_id' => $providerId,
                    'external_ref' => $externalRefs,
                ])
                ->column();

            $existingMap = array_fill_keys(
                array_map('strval', $existingRefs),
                true
            );

            $syncedAt = time();
            $rows = [];

            foreach ($items as $item) {
                $rows[] = [
                    'provider_id' => $providerId,
                    'external_ref' => $item->externalRef,
                    'name' => $item->name,
                    'search_name' => $this->normalizeSearchText(
                        $item->name
                    ),
                    'metadata_json' => $this->encodeMetadata(
                        $item->metadata
                    ),
                    'source_seen_at' => $sourceSeenAt,
                    'synced_at' => $syncedAt,
                    'archived_at' => null,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
            }

            $this->batchUpsert(
                table: DeliveryAreaModel::tableName(),
                columns: [
                    'provider_id',
                    'external_ref',
                    'name',
                    'search_name',
                    'metadata_json',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'created_at',
                    'updated_at',
                ],
                rows: $rows,
                updateColumns: [
                    'name',
                    'search_name',
                    'metadata_json',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'updated_at',
                ],
            );

            return $this->createResult(
                $externalRefs,
                $existingMap
            );
        });
    }

    /**
     * @param list<DeliverySettlementSyncDto> $items
     */
    public function upsertSettlements(array $items, int $sourceSeenAt): DeliveryWriteBatchResultDto {
        $this->assertSourceSeenAt($sourceSeenAt);

        if ($items === []) {
            return DeliveryWriteBatchResultDto::empty();
        }

        [
            $providerCode,
            $externalRefs,
            $areaExternalRefs,
        ] = $this->validateSettlementBatch($items);

        return $this->transactional(function () use (
            $items,
            $providerCode,
            $externalRefs,
            $areaExternalRefs,
            $sourceSeenAt
        ): DeliveryWriteBatchResultDto {
            $providerId = $this->providers
                ->getByCode($providerCode)
                ->id;

            $areaIds = $this->resolveAreaIds(
                $providerId,
                $areaExternalRefs
            );

            $existingRefs = DeliverySettlementModel::find()
                ->select('external_ref')
                ->where([
                    'provider_id' => $providerId,
                    'external_ref' => $externalRefs,
                ])
                ->column();

            $existingMap = array_fill_keys(
                array_map('strval', $existingRefs),
                true
            );

            $syncedAt = time();
            $rows = [];

            foreach ($items as $item) {
                $rows[] = [
                    'provider_id' => $providerId,
                    'area_id' => $areaIds[$item->areaExternalRef],
                    'external_ref' => $item->externalRef,
                    'delivery_ref' => $item->deliveryRef,
                    'name' => $item->name,
                    'search_name' => $this->normalizeSearchText(
                        $item->name
                    ),
                    'present' => $item->present,
                    'settlement_type_code'
                    => $item->settlementTypeCode,
                    'district_name' => $item->districtName,
                    'latitude' => $item->latitude,
                    'longitude' => $item->longitude,
                    'metadata_json' => $this->encodeMetadata(
                        $item->metadata
                    ),
                    'source_seen_at' => $sourceSeenAt,
                    'synced_at' => $syncedAt,
                    'archived_at' => null,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
            }

            $this->batchUpsert(
                table: DeliverySettlementModel::tableName(),
                columns: [
                    'provider_id',
                    'area_id',
                    'external_ref',
                    'delivery_ref',
                    'name',
                    'search_name',
                    'present',
                    'settlement_type_code',
                    'district_name',
                    'latitude',
                    'longitude',
                    'metadata_json',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'created_at',
                    'updated_at',
                ],
                rows: $rows,
                updateColumns: [
                    'area_id',
                    'delivery_ref',
                    'name',
                    'search_name',
                    'present',
                    'settlement_type_code',
                    'district_name',
                    'latitude',
                    'longitude',
                    'metadata_json',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'updated_at',
                ],
                preserveExistingOnNull: [
                    'delivery_ref',
                ],
            );

            return $this->createResult(
                $externalRefs,
                $existingMap
            );
        });
    }

    /**
     * Создаёт или обновляет точки доставки и полностью заменяет
     * их расписания в рамках одной транзакции.
     *
     * @param list<DeliveryPointSyncDto> $items
     */
    public function upsertPoints(array $items, int $sourceSeenAt): DeliveryWriteBatchResultDto {
        $this->assertSourceSeenAt($sourceSeenAt);

        if ($items === []) {
            return DeliveryWriteBatchResultDto::empty();
        }

        [
            $providerCode,
            $externalRefs,
            $settlementExternalRefs,
            $typeCodes,
            $deliveryRefsBySettlement,
        ] = $this->validatePointBatch($items);

        return $this->transactional(function () use (
            $items,
            $providerCode,
            $externalRefs,
            $settlementExternalRefs,
            $typeCodes,
            $deliveryRefsBySettlement,
            $sourceSeenAt
        ): DeliveryWriteBatchResultDto {
            $providerId = $this->providers
                ->getByCode($providerCode)
                ->id;

            $settlements = $this->resolveSettlements(
                providerId: $providerId,
                externalRefs: $settlementExternalRefs,
            );

            $this->synchronizeSettlementDeliveryRefs(
                providerId: $providerId,
                settlements: $settlements,
                deliveryRefsBySettlement: $deliveryRefsBySettlement,
            );

            $typeIds = $this->resolvePointTypeIds(
                $typeCodes
            );

            $existingRefs = DeliveryPointModel::find()
                ->select('external_ref')
                ->where([
                    'provider_id' => $providerId,
                    'external_ref' => $externalRefs,
                ])
                ->column();

            $existingMap = array_fill_keys(
                array_map('strval', $existingRefs),
                true
            );

            $syncedAt = time();
            $rows = [];

            foreach ($items as $item) {
                $rows[] = [
                    'provider_id' => $providerId,

                    'settlement_id' => $settlements[
                    $item->settlementExternalRef
                    ]['id'],

                    'type_id' => $typeIds[$item->typeCode],
                    'external_ref' => $item->externalRef,
                    'external_type_ref' => $item->externalTypeRef,
                    'number' => $item->number,
                    'name' => $item->name,
                    'description' => $item->description,
                    'address' => $item->address,

                    'search_text' => $this->buildPointSearchText(
                        $item
                    ),

                    'latitude' => $item->latitude,
                    'longitude' => $item->longitude,
                    'source_category' => $item->sourceCategory,
                    'source_status' => $item->sourceStatus,
                    'is_active' => (int)$item->isActive,
                    'is_selectable' => (int)$item->isSelectable,

                    'metadata_json' => $this->encodeMetadata(
                        $item->metadata
                    ),

                    'source_seen_at' => $sourceSeenAt,
                    'synced_at' => $syncedAt,
                    'archived_at' => null,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
            }

            $this->batchUpsert(
                table: DeliveryPointModel::tableName(),
                columns: [
                    'provider_id',
                    'settlement_id',
                    'type_id',
                    'external_ref',
                    'external_type_ref',
                    'number',
                    'name',
                    'description',
                    'address',
                    'search_text',
                    'latitude',
                    'longitude',
                    'source_category',
                    'source_status',
                    'is_active',
                    'is_selectable',
                    'metadata_json',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'created_at',
                    'updated_at',
                ],
                rows: $rows,
                updateColumns: [
                    'settlement_id',
                    'type_id',
                    'external_type_ref',
                    'number',
                    'name',
                    'description',
                    'address',
                    'search_text',
                    'latitude',
                    'longitude',
                    'source_category',
                    'source_status',
                    'is_active',
                    'is_selectable',
                    'metadata_json',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'updated_at',
                ],
            );

            $pointIds = $this->resolvePointIds(
                providerId: $providerId,
                externalRefs: $externalRefs,
            );

            $this->replacePointSchedules(
                items: $items,
                pointIds: $pointIds,
                sourceSeenAt: $sourceSeenAt,
                syncedAt: $syncedAt,
            );

            return $this->createResult(
                $externalRefs,
                $existingMap
            );
        });
    }

    /**
     * @param list<DeliveryPointSyncDto> $items
     * @param array<string, int> $pointIds
     */
    private function replacePointSchedules(array $items, array $pointIds, int $sourceSeenAt, int $syncedAt): void {
        $deliveryPointIds = array_values($pointIds);

        DeliveryPointScheduleModel::deleteAll([
            'delivery_point_id' => $deliveryPointIds,
        ]);

        $rows = [];

        foreach ($items as $item) {
            $deliveryPointId = $pointIds[$item->externalRef];

            foreach ($item->schedules as $schedule) {
                $rows[] = [
                    $deliveryPointId,
                    $schedule->weekday,
                    $schedule->intervalNo,
                    $schedule->opensAt,
                    $schedule->closesAt,
                    (int)$schedule->isClosed,
                    $schedule->validFrom,
                    $schedule->validTo,
                    $sourceSeenAt,
                    $syncedAt,
                    $syncedAt,
                    $syncedAt,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        Yii::$app->db
            ->createCommand()
            ->batchInsert(
                DeliveryPointScheduleModel::tableName(),
                [
                    'delivery_point_id',
                    'weekday',
                    'interval_no',
                    'opens_at',
                    'closes_at',
                    'is_closed',
                    'valid_from',
                    'valid_to',
                    'source_seen_at',
                    'synced_at',
                    'created_at',
                    'updated_at',
                ],
                $rows
            )
            ->execute();
    }

    /**
     * @param list<DeliveryAreaSyncDto> $items
     *
     * @return array{string, list<string>}
     */
    private function validateAreaBatch(array $items): array
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                'Delivery area batch must be a list.'
            );
        }

        $providerCode = null;
        $externalRefs = [];
        $seen = [];

        foreach ($items as $item) {
            if (!$item instanceof DeliveryAreaSyncDto) {
                throw new InvalidArgumentException(
                    'Delivery area batch contains an invalid item.'
                );
            }

            $providerCode ??= $item->providerCode;

            if ($item->providerCode !== $providerCode) {
                throw new InvalidArgumentException(
                    'Delivery area batch must contain one provider only.'
                );
            }

            if (isset($seen[$item->externalRef])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate delivery area external reference "%s".',
                    $item->externalRef
                ));
            }

            $seen[$item->externalRef] = true;
            $externalRefs[] = $item->externalRef;
        }

        return [$providerCode, $externalRefs];
    }

    /**
     * @param list<DeliveryPointSyncDto> $items
     *
     * @return array{
     *     string,
     *     list<string>,
     *     list<string>,
     *     list<string>,
     *     array<string, string>
     * }
     */
    private function validatePointBatch(array $items): array
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                'Delivery point batch must be a list.'
            );
        }

        $providerCode = null;
        $externalRefs = [];
        $settlementExternalRefs = [];
        $typeCodes = [];
        $deliveryRefsBySettlement = [];
        $seenPointRefs = [];

        foreach ($items as $item) {
            if (!$item instanceof DeliveryPointSyncDto) {
                throw new InvalidArgumentException(
                    'Delivery point batch contains an invalid item.'
                );
            }

            $providerCode ??= $item->providerCode;

            if ($item->providerCode !== $providerCode) {
                throw new InvalidArgumentException(
                    'Delivery point batch must contain one provider only.'
                );
            }

            if (isset($seenPointRefs[$item->externalRef])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate delivery point external reference "%s".',
                    $item->externalRef
                ));
            }

            $seenPointRefs[$item->externalRef] = true;
            $externalRefs[] = $item->externalRef;

            $settlementExternalRefs[$item->settlementExternalRef]
                = $item->settlementExternalRef;

            $typeCodes[$item->typeCode] = $item->typeCode;

            if ($item->settlementDeliveryRef === null) {
                continue;
            }

            $existingDeliveryRef = $deliveryRefsBySettlement[
            $item->settlementExternalRef
            ] ?? null;

            if (
                $existingDeliveryRef !== null
                && $existingDeliveryRef
                !== $item->settlementDeliveryRef
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Settlement "%s" contains conflicting delivery references "%s" and "%s".',
                    $item->settlementExternalRef,
                    $existingDeliveryRef,
                    $item->settlementDeliveryRef
                ));
            }

            $deliveryRefsBySettlement[
            $item->settlementExternalRef
            ] = $item->settlementDeliveryRef;
        }

        return [
            $providerCode,
            $externalRefs,
            array_values($settlementExternalRefs),
            array_values($typeCodes),
            $deliveryRefsBySettlement,
        ];
    }

    private function buildPointSearchText(DeliveryPointSyncDto $item): string {
        $parts = [];

        foreach ([
                     $item->number,
                     $item->name,
                     $item->description,
                     $item->address,
                 ] as $value) {
            if ($value === null) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $this->normalizeSearchText(
            implode(' ', $parts)
        );
    }

    /**
     * @param list<DeliverySettlementSyncDto> $items
     *
     * @return array{string, list<string>, list<string>}
     */
    private function validateSettlementBatch(array $items): array
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                'Delivery settlement batch must be a list.'
            );
        }

        $providerCode = null;
        $externalRefs = [];
        $areaExternalRefs = [];
        $seen = [];

        foreach ($items as $item) {
            if (!$item instanceof DeliverySettlementSyncDto) {
                throw new InvalidArgumentException(
                    'Delivery settlement batch contains an invalid item.'
                );
            }

            $providerCode ??= $item->providerCode;

            if ($item->providerCode !== $providerCode) {
                throw new InvalidArgumentException(
                    'Delivery settlement batch must contain one provider only.'
                );
            }

            if (isset($seen[$item->externalRef])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate delivery settlement external reference "%s".',
                    $item->externalRef
                ));
            }

            $seen[$item->externalRef] = true;
            $externalRefs[] = $item->externalRef;
            $areaExternalRefs[$item->areaExternalRef]
                = $item->areaExternalRef;
        }

        return [
            $providerCode,
            $externalRefs,
            array_values($areaExternalRefs),
        ];
    }

    /**
     * @param list<string> $areaExternalRefs
     *
     * @return array<string, int>
     */
    private function resolveAreaIds(
        int $providerId,
        array $areaExternalRefs
    ): array {
        $rows = DeliveryAreaModel::find()
            ->select([
                'id',
                'external_ref',
            ])
            ->where([
                'provider_id' => $providerId,
                'external_ref' => $areaExternalRefs,
            ])
            ->asArray()
            ->all();

        $result = [];

        foreach ($rows as $row) {
            $result[(string)$row['external_ref']]
                = (int)$row['id'];
        }

        $missingRefs = array_values(array_diff(
            $areaExternalRefs,
            array_keys($result)
        ));

        if ($missingRefs !== []) {
            throw new OutOfBoundsException(sprintf(
                'Delivery areas were not found for references: %s.',
                implode(', ', $missingRefs)
            ));
        }

        return $result;
    }

    /**
     * @param list<string> $externalRefs
     *
     * @return array<
     *     string,
     *     array{
     *         id: int,
     *         delivery_ref: string|null
     *     }
     * >
     */
    private function resolveSettlements(int $providerId, array $externalRefs): array {
        $rows = DeliverySettlementModel::find()
            ->select([
                'id',
                'external_ref',
                'delivery_ref',
            ])
            ->where([
                'provider_id' => $providerId,
                'external_ref' => $externalRefs,
            ])
            ->asArray()
            ->all();

        $result = [];

        foreach ($rows as $row) {
            $externalRef = (string)$row['external_ref'];

            $result[$externalRef] = [
                'id' => (int)$row['id'],

                'delivery_ref' => $row['delivery_ref'] === null
                    ? null
                    : (string)$row['delivery_ref'],
            ];
        }

        $missingRefs = array_values(array_diff(
            $externalRefs,
            array_keys($result)
        ));

        if ($missingRefs !== []) {
            throw new OutOfBoundsException(sprintf(
                'Delivery settlements were not found for references: %s.',
                implode(', ', $missingRefs)
            ));
        }

        return $result;
    }

    /**
     * @param array<
     *     string,
     *     array{
     *         id: int,
     *         delivery_ref: string|null
     *     }
     * > $settlements
     *
     * @param array<string, string> $deliveryRefsBySettlement
     */
    private function synchronizeSettlementDeliveryRefs(int $providerId, array $settlements, array $deliveryRefsBySettlement): void {
        $updates = [];

        foreach (
            $deliveryRefsBySettlement
            as $settlementExternalRef => $incomingDeliveryRef
        ) {
            $settlement = $settlements[$settlementExternalRef]
                ?? throw new OutOfBoundsException(sprintf(
                    'Delivery settlement "%s" was not resolved.',
                    $settlementExternalRef
                ));

            $existingDeliveryRef = $settlement['delivery_ref'];

            if ($existingDeliveryRef === $incomingDeliveryRef) {
                continue;
            }

            if (
                $existingDeliveryRef !== null
                && $existingDeliveryRef !== ''
            ) {
                throw new UnexpectedValueException(sprintf(
                    'Delivery settlement "%s" already has delivery reference "%s", but source returned "%s".',
                    $settlementExternalRef,
                    $existingDeliveryRef,
                    $incomingDeliveryRef
                ));
            }

            $updates[] = [
                'id' => $settlement['id'],
                'delivery_ref' => $incomingDeliveryRef,
            ];
        }

        if ($updates === []) {
            return;
        }

        $caseParts = [];
        $wherePlaceholders = [];

        $params = [
            ':delivery_ref_provider_id' => $providerId,
            ':delivery_ref_updated_at' => time(),
        ];

        foreach ($updates as $index => $update) {
            $caseIdPlaceholder =
                ':delivery_ref_case_id_' . $index;

            $valuePlaceholder =
                ':delivery_ref_value_' . $index;

            $whereIdPlaceholder =
                ':delivery_ref_where_id_' . $index;

            $caseParts[] = sprintf(
                'WHEN %s THEN %s',
                $caseIdPlaceholder,
                $valuePlaceholder
            );

            $wherePlaceholders[] = $whereIdPlaceholder;

            $params[$caseIdPlaceholder] = $update['id'];
            $params[$valuePlaceholder] = $update['delivery_ref'];
            $params[$whereIdPlaceholder] = $update['id'];
        }

        $sql = sprintf(
            'UPDATE %s SET '
            . '[[delivery_ref]] = CASE [[id]] %s '
            . 'ELSE [[delivery_ref]] END, '
            . '[[updated_at]] = :delivery_ref_updated_at '
            . 'WHERE [[provider_id]] = :delivery_ref_provider_id '
            . 'AND [[id]] IN (%s)',
            DeliverySettlementModel::tableName(),
            implode(' ', $caseParts),
            implode(', ', $wherePlaceholders)
        );

        Yii::$app->db
            ->createCommand($sql, $params)
            ->execute();
    }

    /**
     * @param list<string> $typeCodes
     *
     * @return array<string, int>
     */
    private function resolvePointTypeIds(array $typeCodes): array
    {
        $result = [];

        foreach ($typeCodes as $typeCode) {
            $result[$typeCode] = $this->pointTypes
                ->getByCode($typeCode)
                ->id;
        }

        return $result;
    }

    /**
     * @param list<string> $externalRefs
     *
     * @return array<string, int>
     */
    private function resolvePointIds(int $providerId, array $externalRefs): array {
        $rows = DeliveryPointModel::find()
            ->select([
                'id',
                'external_ref',
            ])
            ->where([
                'provider_id' => $providerId,
                'external_ref' => $externalRefs,
            ])
            ->asArray()
            ->all();

        $result = [];

        foreach ($rows as $row) {
            $result[(string)$row['external_ref']]
                = (int)$row['id'];
        }

        $missingRefs = array_values(array_diff(
            $externalRefs,
            array_keys($result)
        ));

        if ($missingRefs !== []) {
            throw new OutOfBoundsException(sprintf(
                'Delivery points were not found after upsert: %s.',
                implode(', ', $missingRefs)
            ));
        }

        return $result;
    }

    /**
     * @param list<string> $externalRefs
     * @param array<string, true> $existingMap
     */
    private function createResult(
        array $externalRefs,
        array $existingMap
    ): DeliveryWriteBatchResultDto {
        $createdCount = 0;

        foreach ($externalRefs as $externalRef) {
            if (!isset($existingMap[$externalRef])) {
                $createdCount++;
            }
        }

        return new DeliveryWriteBatchResultDto(
            processedCount: count($externalRefs),
            createdCount: $createdCount,
            updatedCount: count($externalRefs) - $createdCount,
        );
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $rows
     * @param list<string> $updateColumns
     * @param list<string> $preserveExistingOnNull
     */
    private function batchUpsert(
        string $table,
        array $columns,
        array $rows,
        array $updateColumns,
        array $preserveExistingOnNull = []
    ): void {
        if ($rows === []) {
            return;
        }

        $params = [];
        $valueGroups = [];
        $parameterIndex = 0;

        foreach ($rows as $row) {
            $placeholders = [];

            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new InvalidArgumentException(sprintf(
                        'Delivery upsert row is missing column "%s".',
                        $column
                    ));
                }

                $placeholder = ':delivery_upsert_'
                    . $parameterIndex++;

                $placeholders[] = $placeholder;
                $params[$placeholder] = $row[$column];
            }

            $valueGroups[] = '('
                . implode(', ', $placeholders)
                . ')';
        }

        $assignments = [];

        foreach ($updateColumns as $column) {
            if (in_array(
                $column,
                $preserveExistingOnNull,
                true
            )) {
                $assignments[] = sprintf(
                    '[[%1$s]] = COALESCE(VALUES([[%1$s]]), [[%1$s]])',
                    $column
                );

                continue;
            }

            $assignments[] = sprintf(
                '[[%1$s]] = VALUES([[%1$s]])',
                $column
            );
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s '
            . 'ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(
                ', ',
                array_map(
                    static fn (string $column): string =>
                        '[[' . $column . ']]',
                    $columns
                )
            ),
            implode(', ', $valueGroups),
            implode(', ', $assignments)
        );

        Yii::$app->db
            ->createCommand($sql, $params)
            ->execute();
    }

    private function normalizeSearchText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(
            $value,
            'UTF-8'
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function encodeMetadata(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }

        return json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    private function assertSourceSeenAt(int $sourceSeenAt): void
    {
        if ($sourceSeenAt < 1) {
            throw new InvalidArgumentException(
                'Delivery sourceSeenAt must be greater than zero.'
            );
        }
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