<?php

declare(strict_types=1);

namespace common\models\delivery;

use yii\db\ActiveQuery;

/**
 * @method DeliveryPointScheduleModel|null one($db = null)
 * @method DeliveryPointScheduleModel[] all($db = null)
 */
final class DeliveryPointScheduleQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byDeliveryPointId(int $deliveryPointId): self
    {
        return $this->andWhere([
            'delivery_point_id' => $deliveryPointId,
        ]);
    }

    public function byWeekday(int $weekday): self
    {
        return $this->andWhere(['weekday' => $weekday]);
    }

    public function effectiveOn(string $date): self
    {
        return $this
            ->andWhere([
                'or',
                ['valid_from' => null],
                ['<=', 'valid_from', $date],
            ])
            ->andWhere([
                'or',
                ['valid_to' => null],
                ['>=', 'valid_to', $date],
            ]);
    }

    public function ordered(): self
    {
        return $this->orderBy([
            'weekday' => SORT_ASC,
            'interval_no' => SORT_ASC,
            'valid_from' => SORT_ASC,
            'id' => SORT_ASC,
        ]);
    }
}