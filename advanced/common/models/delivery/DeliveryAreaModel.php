<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $provider_id
 * @property string $external_ref
 * @property string $name
 * @property string $search_name
 * @property string|null $metadata_json
 * @property int $source_seen_at
 * @property int $synced_at
 * @property int|null $archived_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryProviderModel $provider
 * @property DeliverySettlementModel[] $settlements
 */
class DeliveryAreaModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_area}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                [
                    'provider_id',
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
                    'source_seen_at',
                    'synced_at',
                    'archived_at',
                    'created_at',
                    'updated_at',
                ],
                'integer',
            ],

            [['metadata_json'], 'string'],
            [['external_ref'], 'string', 'max' => 128],
            [['name', 'search_name'], 'string', 'max' => 255],

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
        ]);
    }

    public function getProvider(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryProviderModel::class,
            ['id' => 'provider_id']
        );
    }

    public function getSettlements(): ActiveQuery
    {
        return $this->hasMany(
            DeliverySettlementModel::class,
            [
                'area_id' => 'id',
                'provider_id' => 'provider_id',
            ]
        );
    }

    public function getIsArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public static function find(): DeliveryAreaQuery
    {
        return new DeliveryAreaQuery(static::class);
    }
}