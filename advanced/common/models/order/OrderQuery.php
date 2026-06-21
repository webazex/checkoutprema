<?php

namespace common\models\order;

use yii\db\ActiveQuery;

class OrderQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byIds(array $ids): self
    {
        return $this->andWhere(['id' => $ids]);
    }

    public function byHash(string $hash): self
    {
        return $this->andWhere(['hash' => $hash]);
    }

    public function byCartId(int $cartId): self
    {
        return $this->andWhere(['cart_id' => $cartId]);
    }

    public function forCustomer(int $customerId): self
    {
        return $this->andWhere(['customer_id' => $customerId]);
    }

    public function new(): self
    {
        return $this->status(OrderModel::STATUS_NEW);
    }

    public function status(string $status): self
    {
        return $this->andWhere(['status' => $status]);
    }

    public function pending(): self
    {
        return $this->status(OrderModel::STATUS_PENDING);
    }

    public function paid(): self
    {
        return $this->status(OrderModel::STATUS_PAID);
    }

    public function cancelled(): self
    {
        return $this->status(OrderModel::STATUS_CANCELLED);
    }

    public function paymentPending(): self
    {
        return $this->paymentStatus('pending');
    }

    public function paymentStatus(string $paymentStatus): self
    {
        return $this->andWhere(['payment_status' => $paymentStatus]);
    }

    public function paymentPaid(): self
    {
        return $this->paymentStatus('paid');
    }

    public function paymentFailed(): self
    {
        return $this->paymentStatus('failed');
    }

    public function sourceDirect(): self
    {
        return $this->andWhere(['source_type' => 'direct']);
    }

    public function sourceWix(): self
    {
        return $this->andWhere(['source_type' => 'wix']);
    }

    public function newestFirst(): self
    {
        return $this->orderBy(['id' => SORT_DESC]);
    }

    public function placedFirst(): self
    {
        return $this->orderBy([
            'placed_at' => SORT_DESC,
            'id' => SORT_DESC,
        ]);
    }
}