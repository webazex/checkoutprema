<?php

namespace common\models\payment;

use yii\db\ActiveQuery;

class PaymentLogQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byIds(array $ids): self
    {
        return $this->andWhere(['id' => $ids]);
    }

    public function forPayment(int $paymentId): self
    {
        return $this->andWhere(['payment_id' => $paymentId]);
    }

    public function forOrder(int $orderId): self
    {
        return $this->andWhere(['order_id' => $orderId]);
    }

    public function provider(string $provider): self
    {
        return $this->andWhere(['provider' => $provider]);
    }

    public function byExternalId(string $externalId): self
    {
        return $this->andWhere(['external_id' => $externalId]);
    }

    public function request(): self
    {
        return $this->eventType(PaymentLogModel::EVENT_REQUEST);
    }

    public function eventType(string $eventType): self
    {
        return $this->andWhere(['event_type' => $eventType]);
    }

    public function response(): self
    {
        return $this->eventType(PaymentLogModel::EVENT_RESPONSE);
    }

    public function callback(): self
    {
        return $this->eventType(PaymentLogModel::EVENT_CALLBACK);
    }

    public function webhook(): self
    {
        return $this->eventType(PaymentLogModel::EVENT_WEBHOOK);
    }

    public function statusChange(): self
    {
        return $this->eventType(PaymentLogModel::EVENT_STATUS_CHANGE);
    }

    public function error(): self
    {
        return $this->eventType(PaymentLogModel::EVENT_ERROR);
    }

    public function incoming(): self
    {
        return $this->direction(PaymentLogModel::DIRECTION_IN);
    }

    public function direction(string $direction): self
    {
        return $this->andWhere(['direction' => $direction]);
    }

    public function outgoing(): self
    {
        return $this->direction(PaymentLogModel::DIRECTION_OUT);
    }

    public function internal(): self
    {
        return $this->direction(PaymentLogModel::DIRECTION_INTERNAL);
    }

    public function status(string $status): self
    {
        return $this->andWhere(['status' => $status]);
    }

    public function newestFirst(): self
    {
        return $this->orderBy([
            'id' => SORT_DESC,
        ]);
    }

    public function createdAsc(): self
    {
        return $this->orderBy([
            'created_at' => SORT_ASC,
            'id' => SORT_ASC,
        ]);
    }

    public function createdDesc(): self
    {
        return $this->orderBy([
            'created_at' => SORT_DESC,
            'id' => SORT_DESC,
        ]);
    }
}