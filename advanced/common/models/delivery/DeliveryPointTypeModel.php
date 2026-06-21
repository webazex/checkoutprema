<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryPointModel[] $points
 */
class DeliveryPointTypeModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_point_type}}';
    }

    public static function find(): DeliveryPointTypeQuery
    {
        return new DeliveryPointTypeQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['code', 'name'], 'required'],
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

    public function getPoints(): ActiveQuery
    {
        return $this->hasMany(
            DeliveryPointModel::class,
            ['type_id' => 'id']
        );
    }
}