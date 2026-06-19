<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $is_active
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryAreaModel[] $areas
 * @property DeliverySettlementModel[] $settlements
 * @property DeliveryPointModel[] $points
 * @property DeliverySyncStateModel[] $syncStates
 */
class DeliveryProviderModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_provider}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['code', 'name'], 'required'],
            [['is_active'], 'boolean'],
            [['sort_order', 'created_at', 'updated_at'], 'integer'],

            [['code'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 255],

            [
                ['code'],
                'match',
                'pattern' => '/^[a-z][a-z0-9_]{0,63}$/',
            ],

            [['code'], 'unique'],
        ]);
    }

    public function getAreas(): ActiveQuery
    {
        return $this->hasMany(
            DeliveryAreaModel::class,
            ['provider_id' => 'id']
        );
    }

    public function getSettlements(): ActiveQuery
    {
        return $this->hasMany(
            DeliverySettlementModel::class,
            ['provider_id' => 'id']
        );
    }

    public function getPoints(): ActiveQuery
    {
        return $this->hasMany(
            DeliveryPointModel::class,
            ['provider_id' => 'id']
        );
    }

    public function getSyncStates(): ActiveQuery
    {
        return $this->hasMany(
            DeliverySyncStateModel::class,
            ['provider_id' => 'id']
        );
    }

    public function getIsActive(): bool
    {
        return (bool)$this->is_active;
    }

    public static function find(): DeliveryProviderQuery
    {
        return new DeliveryProviderQuery(static::class);
    }
}