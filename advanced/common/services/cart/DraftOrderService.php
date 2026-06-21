<?php

namespace common\services\cart;

use RuntimeException;
use Yii;
use common\models\Order;
use common\models\customer\CustomerModel;
use common\dto\WixCartPayloadDto;

/**
 * Работа с draft-order / текущей корзиной.
 */
class DraftOrderService
{
    public function findOrCreateDraftOrder(?CustomerModel $customer, WixCartPayloadDto $payload): Order
    {
        // 1. Если customer известен — пробуем найти его активный draft
        if ($customer !== null) {
            $order = $this->findDraftByCustomerHash($customer->hash);
            if ($order !== null) {
                return $order;
            }

            return $this->createDraft($customer->hash);
        }

        // 2. Пробуем найти draft по session hash
        $sessionHash = Yii::$app->session->get('customer_hash');
        if (!empty($sessionHash)) {
            $order = $this->findDraftByCustomerHash($sessionHash);
            if ($order !== null) {
                return $order;
            }
        }

        // 3. Если payload уже принёс customerHash — пробуем по нему
        if (!empty($payload->customerHash)) {
            $order = $this->findDraftByCustomerHash($payload->customerHash);
            if ($order !== null) {
                return $order;
            }
        }

        // 4. Иначе создаём новый draft
        return $this->createDraft(Yii::$app->security->generateRandomString(32));
    }

    protected function findDraftByCustomerHash(string $customerHash): ?Order
    {
        return Order::findOne([
            'customer_hash' => $customerHash,
            'status' => Order::STATUS_DRAFT,
        ]);
    }

    protected function createDraft(string $customerHash): Order
    {
        $order = new Order();
        $order->customer_hash = $customerHash;
        $order->status = Order::STATUS_DRAFT;

        // если у Order есть TimestampBehavior, можно не ставить руками
        if ($order->hasAttribute('created_at') && empty($order->created_at)) {
            $order->created_at = time();
        }

        if ($order->hasAttribute('updated_at') && empty($order->updated_at)) {
            $order->updated_at = time();
        }

        if (!$order->save()) {
            Yii::error([
                'message' => 'Failed to create draft order',
                'errors' => $order->errors,
            ], __METHOD__);

            throw new RuntimeException('Failed to create draft order.');
        }

        return $order;
    }
}