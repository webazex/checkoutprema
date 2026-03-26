<?php

namespace common\models\payment;

use yii\db\ActiveQuery;

class PaymentQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byIds(array $ids): self
    {
        return $this->andWhere(['id' => $ids]);
    }

    public function forOrder(int $orderId): self
    {
        return $this->andWhere(['order_id' => $orderId]);
    }

    public function forCustomer(int $customerId): self
    {
        return $this->andWhere(['customer_id' => $customerId]);
    }

    public function provider(string $provider): self
    {
        return $this->andWhere(['provider' => $provider]);
    }

    public function wayForPay(): self
    {
        return $this->provider(PaymentModel::PROVIDER_WAYFORPAY);
    }

    public function byExternalId(string $externalId): self
    {
        return $this->andWhere(['external_id' => $externalId]);
    }

    public function byExternalOrderId(string $externalOrderId): self
    {
        return $this->andWhere(['external_order_id' => $externalOrderId]);
    }

    public function byIdempotencyKey(string $idempotencyKey): self
    {
        return $this->andWhere(['idempotency_key' => $idempotencyKey]);
    }

    public function status(string $status): self
    {
        return $this->andWhere(['status' => $status]);
    }

    public function statusNew(): self
    {
        return $this->status(PaymentModel::STATUS_NEW);
    }

    public function pending(): self
    {
        return $this->status(PaymentModel::STATUS_PENDING);
    }

    public function authorized(): self
    {
        return $this->status(PaymentModel::STATUS_AUTHORIZED);
    }

    public function paid(): self
    {
        return $this->status(PaymentModel::STATUS_PAID);
    }

    public function failed(): self
    {
        return $this->status(PaymentModel::STATUS_FAILED);
    }

    public function cancelled(): self
    {
        return $this->status(PaymentModel::STATUS_CANCELLED);
    }

    public function refunded(): self
    {
        return $this->status(PaymentModel::STATUS_REFUNDED);
    }

    public function final(): self
    {
        return $this->andWhere(['status' => PaymentModel::FINAL_STATUSES]);
    }

    public function successful(): self
    {
        return $this->andWhere(['status' => PaymentModel::SUCCESS_STATUSES]);
    }

    public function unsuccessful(): self
    {
        return $this->andWhere(['status' => PaymentModel::FAILURE_STATUSES]);
    }

    public function newestFirst(): self
    {
        return $this->orderBy(['id' => SORT_DESC]);
    }

    public function createdDesc(): self
    {
        return $this->orderBy([
            'created_at' => SORT_DESC,
            'id' => SORT_DESC,
        ]);
    }

    public function paidDesc(): self
    {
        return $this->orderBy([
            'paid_at' => SORT_DESC,
            'id' => SORT_DESC,
        ]);
    }
}