<?php

declare(strict_types=1);

namespace common\models\delivery;

use yii\db\ActiveQuery;

/**
 * @method DeliveryProviderModel|null one($db = null)
 * @method DeliveryProviderModel[] all($db = null)
 */
final class DeliveryProviderQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byCode(string $code): self
    {
        return $this->andWhere(['code' => $code]);
    }

    public function active(): self
    {
        return $this->andWhere(['is_active' => true]);
    }

    public function ordered(): self
    {
        return $this->orderBy([
            'sort_order' => SORT_ASC,
            'id' => SORT_ASC,
        ]);
    }
}