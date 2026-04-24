<?php

namespace common\services\cart;

use Yii;
use common\models\Order;
use common\models\OrderItem;

/**
 * Сливает входящие позиции в текущий draft order.
 */
class CartMergeService
{
    public function mergeIncomingItemsIntoOrder(Order $order, array $items): void
    {
        foreach ($items as $incomingItem) {
            $productId = (int)($incomingItem['productId'] ?? 0);
            $qty = max(1, (int)($incomingItem['qty'] ?? 1));

            if ($productId < 1) {
                continue;
            }

            $item = OrderItem::findOne([
                'order_id' => $order->id,
                'product_id' => $productId,
            ]);

            if ($item === null) {
                $item = new OrderItem();
                $item->order_id = $order->id;
                $item->product_id = $productId;
                $item->quantity = 0;
                $item->price = $this->resolveProductPrice($productId);
            }

            $item->quantity += $qty;
            $item->price_total = (float)$item->price * (int)$item->quantity;

            if (!$item->save()) {
                Yii::error([
                    'message' => 'Failed to merge item into order',
                    'orderId' => $order->id,
                    'productId' => $productId,
                    'errors' => $item->errors,
                ], __METHOD__);

                throw new \RuntimeException('Failed to merge item into draft order.');
            }
        }

        if ($order->hasAttribute('updated_at')) {
            $order->updated_at = time();
            $order->save(false, ['updated_at']);
        }
    }

    /**
     * Временная заглушка.
     * Позже заменить на нормальное получение цены из Product.
     */
    protected function resolveProductPrice(int $productId): float
    {
        return 1499.00;
    }
}