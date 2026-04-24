<?php

namespace common\models\cart;

use yii\db\ActiveQuery;

class CartQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['status' => CartModel::STATUS_ACTIVE]);
    }

    public function abandoned(): self
    {
        return $this->andWhere(['status' => CartModel::STATUS_ABANDONED]);
    }

    public function expired(): self
    {
        return $this->andWhere(['status' => CartModel::STATUS_EXPIRED]);
    }

    public function converted(): self
    {
        return $this->andWhere(['status' => CartModel::STATUS_CONVERTED]);
    }

    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byHash(string $hash): self
    {
        return $this->andWhere(['hash' => $hash]);
    }

    public function bySessionKey(string $sessionKey): self
    {
        return $this->andWhere(['session_key' => $sessionKey]);
    }

    public function byCustomerId(int $customerId): self
    {
        return $this->andWhere(['customer_id' => $customerId]);
    }

    public function guest(): self
    {
        return $this->andWhere(['customer_id' => null]);
    }

    public function forCustomer(int $customerId): self
    {
        return $this->andWhere(['customer_id' => $customerId]);
    }

    public function sourceDirect(): self
    {
        return $this->andWhere(['source_type' => CartModel::SOURCE_DIRECT]);
    }

    public function sourceWix(): self
    {
        return $this->andWhere(['source_type' => CartModel::SOURCE_WIX]);
    }

    public function lastActiveFirst(): self
    {
        return $this->orderBy([
            'last_activity_at' => SORT_DESC,
            'id' => SORT_DESC,
        ]);
    }

    public function newestFirst(): self
    {
        return $this->orderBy([
            'id' => SORT_DESC,
        ]);
    }
}