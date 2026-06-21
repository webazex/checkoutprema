<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $provider_id
 * @property int $settlement_id
 * @property int $type_id
 * @property string $external_ref
 * @property string|null $external_type_ref
 * @property string|null $number
 * @property string|null $name
 * @property string|null $description
 * @property string $address
 * @property string $search_text
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $source_category
 * @property string|null $source_status
 * @property int $is_active
 * @property int $is_selectable
 * @property string|null $metadata_json
 * @property int $source_seen_at
 * @property int $synced_at
 * @property int|null $archived_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryProviderModel $provider
 * @property DeliverySettlementModel $settlement
 * @property DeliveryPointTypeModel $type
 * @property DeliveryPointScheduleModel[] $schedules
 */
class DeliveryPointModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_point}}';
    }

    public static function find(): DeliveryPointQuery
    {
        return new DeliveryPointQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                [
                    'provider_id',
                    'settlement_id',
                    'type_id',
                    'external_ref',
                    'address',
                    'search_text',
                    'source_seen_at',
                    'synced_at',
                ],
                'required',
            ],

            [
                [
                    'provider_id',
                    'settlement_id',
                    'type_id',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'created_at',
                    'updated_at',
                ],
                'integer',
            ],

            [['is_active', 'is_selectable'], 'default', 'value' => false],
            [['is_active', 'is_selectable'], 'boolean'],

            [
                ['is_selectable'],
                'validateSelectableState',
            ],

            [['name', 'description', 'address', 'search_text', 'metadata_json'], 'string'],
            [['latitude', 'longitude'], 'number'],

            [['external_ref', 'external_type_ref'], 'string', 'max' => 128],
            [['number'], 'string', 'max' => 32],
            [['source_category', 'source_status'], 'string', 'max' => 64],

            [
                ['external_ref'],
                'unique',
                'targetAttribute' => [
                    'provider_id',
                    'external_ref',
                ],
            ],

            [
                ['provider_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => DeliveryProviderModel::class,
                'targetAttribute' => ['provider_id' => 'id'],
            ],

            [
                ['settlement_id', 'provider_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => DeliverySettlementModel::class,
                'targetAttribute' => [
                    'settlement_id' => 'id',
                    'provider_id' => 'provider_id',
                ],
            ],

            [
                ['type_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => DeliveryPointTypeModel::class,
                'targetAttribute' => ['type_id' => 'id'],
            ],

            [['latitude'], 'number', 'min' => -90, 'max' => 90],
            [['longitude'], 'number', 'min' => -180, 'max' => 180],

            [
                ['latitude'],
                'validateCoordinatesPair',
                'skipOnEmpty' => false,
            ],
        ]);
    }

    public function validateCoordinatesPair(string $attribute): void
    {
        $hasLatitude = $this->latitude !== null && $this->latitude !== '';
        $hasLongitude = $this->longitude !== null && $this->longitude !== '';

        if ($hasLatitude === $hasLongitude) {
            return;
        }

        $this->addError(
            $attribute,
            'Latitude and longitude must either both be specified or both be null.'
        );
    }

    public function validateSelectableState(string $attribute): void
    {
        if (!(bool)$this->is_selectable || (bool)$this->is_active) {
            return;
        }

        $this->addError(
            $attribute,
            'Selectable delivery point must also be active.'
        );
    }

    public function getProvider(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryProviderModel::class,
            ['id' => 'provider_id']
        );
    }

    public function getSettlement(): ActiveQuery
    {
        return $this->hasOne(
            DeliverySettlementModel::class,
            [
                'id' => 'settlement_id',
                'provider_id' => 'provider_id',
            ]
        );
    }

    public function getType(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryPointTypeModel::class,
            ['id' => 'type_id']
        );
    }

    public function getSchedules(): ActiveQuery
    {
        return $this->hasMany(
            DeliveryPointScheduleModel::class,
            ['delivery_point_id' => 'id']
        )->orderBy([
            'weekday' => SORT_ASC,
            'interval_no' => SORT_ASC,
            'valid_from' => SORT_ASC,
        ]);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->is_active;
    }

    public function getIsSelectable(): bool
    {
        return (bool)$this->is_selectable;
    }

    public function getIsArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getHasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}