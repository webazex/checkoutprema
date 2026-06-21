<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $delivery_point_id
 * @property int $weekday
 * @property int $interval_no
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property int $is_closed
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property string $valid_from_key
 * @property int $source_seen_at
 * @property int $synced_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryPointModel $deliveryPoint
 */
class DeliveryPointScheduleModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_point_schedule}}';
    }

    public static function find(): DeliveryPointScheduleQuery
    {
        return new DeliveryPointScheduleQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                [
                    'delivery_point_id',
                    'weekday',
                    'interval_no',
                    'source_seen_at',
                    'synced_at',
                ],
                'required',
            ],

            [
                [
                    'delivery_point_id',
                    'weekday',
                    'interval_no',
                    'source_seen_at',
                    'synced_at',
                    'created_at',
                    'updated_at',
                ],
                'integer',
            ],

            [['is_closed'], 'default', 'value' => false],
            [['is_closed'], 'boolean'],

            [['weekday'], 'integer', 'min' => 1, 'max' => 7],
            [['interval_no'], 'integer', 'min' => 1],

            [['opens_at', 'closes_at'], 'date', 'format' => 'php:H:i:s'],
            [['valid_from', 'valid_to'], 'date', 'format' => 'php:Y-m-d'],

            [
                ['delivery_point_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => DeliveryPointModel::class,
                'targetAttribute' => ['delivery_point_id' => 'id'],
            ],

            [
                ['is_closed'],
                'validateWorkingHours',
            ],

            [
                ['valid_to'],
                'validateValidityPeriod',
            ],
        ]);
    }

    public function validateWorkingHours(string $attribute): void
    {
        $hasOpensAt = $this->opens_at !== null && $this->opens_at !== '';
        $hasClosesAt = $this->closes_at !== null && $this->closes_at !== '';

        if ((bool)$this->is_closed) {
            if (!$hasOpensAt && !$hasClosesAt) {
                return;
            }

            $this->addError(
                $attribute,
                'Closed schedule interval must not contain working hours.'
            );

            return;
        }

        if ($hasOpensAt && $hasClosesAt) {
            return;
        }

        $this->addError(
            $attribute,
            'Open schedule interval must contain both opening and closing time.'
        );
    }

    public function validateValidityPeriod(string $attribute): void
    {
        if (
            $this->valid_from === null ||
            $this->valid_from === '' ||
            $this->valid_to === null ||
            $this->valid_to === ''
        ) {
            return;
        }

        if ($this->valid_from <= $this->valid_to) {
            return;
        }

        $this->addError(
            $attribute,
            'Schedule valid_from must not be later than valid_to.'
        );
    }

    public function getDeliveryPoint(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryPointModel::class,
            ['id' => 'delivery_point_id']
        );
    }

    public function getIsClosed(): bool
    {
        return (bool)$this->is_closed;
    }
}