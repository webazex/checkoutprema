<?php

declare(strict_types=1);

namespace common\mappers\delivery;

use common\dto\delivery\DeliveryAreaReadDto;
use common\dto\delivery\DeliveryPointReadDto;
use common\dto\delivery\DeliveryPointScheduleReadDto;
use common\dto\delivery\DeliveryPointTypeReadDto;
use common\dto\delivery\DeliveryProviderReadDto;
use common\dto\delivery\DeliverySettlementReadDto;
use common\dto\delivery\DeliverySyncStateReadDto;
use common\models\delivery\DeliveryAreaModel;
use common\models\delivery\DeliveryPointModel;
use common\models\delivery\DeliveryPointScheduleModel;
use common\models\delivery\DeliveryPointTypeModel;
use common\models\delivery\DeliveryProviderModel;
use common\models\delivery\DeliverySettlementModel;
use common\models\delivery\DeliverySyncStateModel;
use JsonException;
use LogicException;
use UnexpectedValueException;
use yii\db\BaseActiveRecord;

final class DeliveryReadMapper
{
    public function mapProvider(DeliveryProviderModel $model): DeliveryProviderReadDto
    {
        return new DeliveryProviderReadDto(
            id: (int)$model->id,
            code: (string)$model->code,
            name: (string)$model->name,
            isActive: $model->getIsActive(),
            sortOrder: (int)$model->sort_order,
        );
    }

    public function mapPointType(DeliveryPointTypeModel $model): DeliveryPointTypeReadDto
    {
        return new DeliveryPointTypeReadDto(
            id: (int)$model->id,
            code: (string)$model->code,
            name: (string)$model->name,
            sortOrder: (int)$model->sort_order,
        );
    }

    public function mapArea(DeliveryAreaModel $model): DeliveryAreaReadDto
    {
        $provider = $this->requireProvider($model);

        return new DeliveryAreaReadDto(
            id: (int)$model->id,
            providerId: (int)$model->provider_id,
            providerCode: (string)$provider->code,
            externalRef: (string)$model->external_ref,
            name: (string)$model->name,
        );
    }

    public function mapSettlement(DeliverySettlementModel $model): DeliverySettlementReadDto
    {
        $provider = $this->requireProvider($model);
        $area = $this->requireArea($model);

        return new DeliverySettlementReadDto(
            id: (int)$model->id,
            providerId: (int)$model->provider_id,
            providerCode: (string)$provider->code,
            areaId: (int)$model->area_id,
            areaExternalRef: (string)$area->external_ref,
            areaName: (string)$area->name,
            externalRef: (string)$model->external_ref,
            deliveryRef: $this->nullableString($model->delivery_ref),
            name: (string)$model->name,
            present: $this->nullableString($model->present),
            settlementTypeCode: $this->nullableString($model->settlement_type_code),
            districtName: $this->nullableString($model->district_name),
            latitude: $this->nullableFloat($model->latitude),
            longitude: $this->nullableFloat($model->longitude),
        );
    }

    public function mapSchedule(DeliveryPointScheduleModel $model): DeliveryPointScheduleReadDto
    {
        return new DeliveryPointScheduleReadDto(
            weekday: (int)$model->weekday,
            intervalNo: (int)$model->interval_no,
            opensAt: $this->nullableString($model->opens_at),
            closesAt: $this->nullableString($model->closes_at),
            isClosed: $model->getIsClosed(),
            validFrom: $this->nullableString($model->valid_from),
            validTo: $this->nullableString($model->valid_to),
        );
    }

    public function mapPoint(DeliveryPointModel $model): DeliveryPointReadDto
    {
        $provider = $this->requireProvider($model);
        $settlement = $this->requireSettlement($model);
        $area = $this->requireArea($settlement);
        $type = $this->requirePointType($model);
        $scheduleModels = $this->requireSchedules($model);

        $schedules = [];

        foreach ($scheduleModels as $scheduleModel) {
            $schedules[] = $this->mapSchedule($scheduleModel);
        }

        return new DeliveryPointReadDto(
            id: (int)$model->id,
            providerId: (int)$model->provider_id,
            providerCode: (string)$provider->code,
            providerName: (string)$provider->name,
            areaId: (int)$area->id,
            areaExternalRef: (string)$area->external_ref,
            areaName: (string)$area->name,
            settlementId: (int)$settlement->id,
            settlementExternalRef: (string)$settlement->external_ref,
            settlementDeliveryRef: $this->nullableString($settlement->delivery_ref),
            settlementName: (string)$settlement->name,
            typeId: (int)$type->id,
            typeCode: (string)$type->code,
            typeName: (string)$type->name,
            externalRef: (string)$model->external_ref,
            externalTypeRef: $this->nullableString($model->external_type_ref),
            number: $this->nullableString($model->number),
            name: $this->nullableString($model->name),
            description: $this->nullableString($model->description),
            address: (string)$model->address,
            latitude: $this->nullableFloat($model->latitude),
            longitude: $this->nullableFloat($model->longitude),
            sourceCategory: $this->nullableString($model->source_category),
            sourceStatus: $this->nullableString($model->source_status),
            isActive: $model->getIsActive(),
            isSelectable: $model->getIsSelectable(),
            metadata: $this->decodeMetadata(
                $model->metadata_json,
                sprintf('delivery point #%d', (int)$model->id)
            ),
            schedules: $schedules,
        );
    }

    public function mapSyncState(DeliverySyncStateModel $model): DeliverySyncStateReadDto
    {
        $provider = $this->requireProvider($model);

        return new DeliverySyncStateReadDto(
            id: (int)$model->id,
            providerId: (int)$model->provider_id,
            providerCode: (string)$provider->code,
            scope: $model->getScopeEnum(),
            scopeExternalRef: (string)$model->scope_external_ref,
            status: $model->getStatusEnum(),
            runToken: $this->nullableString($model->run_token),
            cursor: $this->nullableString($model->cursor),
            startedAt: $this->nullableInt($model->started_at),
            heartbeatAt: $this->nullableInt($model->heartbeat_at),
            finishedAt: $this->nullableInt($model->finished_at),
            lastSuccessAt: $this->nullableInt($model->last_success_at),
            lastErrorAt: $this->nullableInt($model->last_error_at),
            lastErrorType: $this->nullableString($model->last_error_type),
            lastErrorCode: $this->nullableString($model->last_error_code),
            lastErrorMessage: $this->nullableString($model->last_error_message),
            sourceTotalCount: $this->nullableInt($model->source_total_count),
            processedCount: (int)$model->processed_count,
            createdCount: (int)$model->created_count,
            updatedCount: (int)$model->updated_count,
            archivedCount: (int)$model->archived_count,
        );
    }

    private function requireProvider(
        DeliveryAreaModel|DeliverySettlementModel|DeliveryPointModel|DeliverySyncStateModel $model
    ): DeliveryProviderModel {
        $relation = $this->requireRelation($model, 'provider');

        if (!$relation instanceof DeliveryProviderModel) {
            throw new LogicException(sprintf(
                'Relation "%s.provider" must contain DeliveryProviderModel.',
                $model::class
            ));
        }

        return $relation;
    }

    private function requireArea(DeliverySettlementModel $model): DeliveryAreaModel
    {
        $relation = $this->requireRelation($model, 'area');

        if (!$relation instanceof DeliveryAreaModel) {
            throw new LogicException(sprintf(
                'Relation "%s.area" must contain DeliveryAreaModel.',
                $model::class
            ));
        }

        return $relation;
    }

    private function requireSettlement(DeliveryPointModel $model): DeliverySettlementModel
    {
        $relation = $this->requireRelation($model, 'settlement');

        if (!$relation instanceof DeliverySettlementModel) {
            throw new LogicException(sprintf(
                'Relation "%s.settlement" must contain DeliverySettlementModel.',
                $model::class
            ));
        }

        return $relation;
    }

    private function requirePointType(DeliveryPointModel $model): DeliveryPointTypeModel
    {
        $relation = $this->requireRelation($model, 'type');

        if (!$relation instanceof DeliveryPointTypeModel) {
            throw new LogicException(sprintf(
                'Relation "%s.type" must contain DeliveryPointTypeModel.',
                $model::class
            ));
        }

        return $relation;
    }

    /**
     * @return list<DeliveryPointScheduleModel>
     */
    private function requireSchedules(DeliveryPointModel $model): array
    {
        $relation = $this->requireRelation($model, 'schedules');

        if (!is_array($relation) || !array_is_list($relation)) {
            throw new LogicException(sprintf(
                'Relation "%s.schedules" must contain a list.',
                $model::class
            ));
        }

        foreach ($relation as $schedule) {
            if (!$schedule instanceof DeliveryPointScheduleModel) {
                throw new LogicException(sprintf(
                    'Relation "%s.schedules" contains an invalid model.',
                    $model::class
                ));
            }
        }

        return $relation;
    }

    private function requireRelation(BaseActiveRecord $model, string $relation): mixed
    {
        if (!$model->isRelationPopulated($relation)) {
            throw new LogicException(sprintf(
                'Relation "%s.%s" must be eager-loaded before mapping.',
                $model::class,
                $relation
            ));
        }

        $relations = $model->getRelatedRecords();

        return $relations[$relation] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $value, string $context): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Metadata for %s must be a JSON string or array.',
                $context
            ));
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new UnexpectedValueException(
                sprintf('Metadata for %s contains invalid JSON.', $context),
                previous: $exception
            );
        }

        if (!is_array($decoded)) {
            throw new UnexpectedValueException(sprintf(
                'Metadata for %s must decode to an array.',
                $context
            ));
        }

        return $decoded;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string)$value;

        return trim($value) === '' ? null : $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}