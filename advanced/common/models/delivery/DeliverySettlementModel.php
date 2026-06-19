<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $provider_id
 * @property int $area_id
 * @property string $external_ref
 * @property string|null $delivery_ref
 * @property string $name
 * @property string $search_name
 * @property string|null $present
 * @property string|null $settlement_type_code
 * @property string|null $district_name
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $metadata_json
 * @property int $source_seen_at
 * @property int $synced_at
 * @property int|null $archived_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryProviderModel $provider
 * @property DeliveryAreaModel $area
 * @property DeliveryPointModel[] $points
 */
class DeliverySettlementModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_settlement}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                [
                    'provider_id',
                    'area_id',
                    'external_ref',
                    'name',
                    'search_name',
                    'source_seen_at',
                    'synced_at',
                ],
                'required',
            ],

            [
                [
                    'provider_id',
                    'area_id',
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'created_at',
                    'updated_at',
                ],
                'integer',
            ],

            [['present', 'metadata_json'], 'string'],
            [['latitude', 'longitude'], 'number'],

            [['external_ref', 'delivery_ref'], 'string', 'max' => 128],
            [['name', 'search_name', 'district_name'], 'string', 'max' => 255],
            [['settlement_type_code'], 'string', 'max' => 32],

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
                ['area_id', 'provider_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => DeliveryAreaModel::class,
                'targetAttribute' => [
                    'area_id' => 'id',
                    'provider_id' => 'provider_id',
                ],
            ],

            [['latitude'], 'number', 'min' => -90, 'max' => 90],
            [['longitude'], 'number', 'min' => -180, 'max' => 180],

            [
                ['latitude'],
                'validateCoordinatesPair',
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

    public function getProvider(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryProviderModel::class,
            ['id' => 'provider_id']
        );
    }

    public function getArea(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryAreaModel::class,
            [
                'id' => 'area_id',
                'provider_id' => 'provider_id',
            ]
        );
    }

    public function getPoints(): ActiveQuery
    {
        return $this->hasMany(
            DeliveryPointModel::class,
            [
                'settlement_id' => 'id',
                'provider_id' => 'provider_id',
            ]
        );
    }

    public function getIsArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getHasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public static function find(): DeliverySettlementQuery
    {
        return new DeliverySettlementQuery(static::class);
    }
}