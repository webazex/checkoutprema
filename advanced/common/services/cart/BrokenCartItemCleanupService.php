<?php

declare(strict_types=1);

namespace common\services\cart;

use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use yii\db\Query;
use yii\helpers\Json;

final class BrokenCartItemCleanupService
{
    public function cleanup(): array
    {
        $stats = [
            'brokenItemsFound' => 0,
            'brokenItemsDeleted' => 0,
            'affectedCarts' => 0,
            'recalculatedCarts' => 0,
            'errors' => 0,
        ];

        $brokenRows = (new Query())
            ->select(['ci.id', 'ci.cart_id'])
            ->from(['ci' => CartItemModel::tableName()])
            ->leftJoin(['p' => 'product'], 'p.id = ci.product_id')
            ->where(['p.id' => null])
            ->all();

        if ($brokenRows === []) {
            return $stats;
        }

        $stats['brokenItemsFound'] = count($brokenRows);

        $brokenItemIds = [];
        $cartIds = [];

        foreach ($brokenRows as $row) {
            $brokenItemIds[] = (int)$row['id'];
            $cartIds[] = (int)$row['cart_id'];
        }

        $cartIds = array_values(array_unique(array_filter($cartIds)));
        $stats['affectedCarts'] = count($cartIds);

        if (!empty($brokenItemIds)) {
            $deleted = CartItemModel::deleteAll(['id' => $brokenItemIds]);
            $stats['brokenItemsDeleted'] = (int)$deleted;
        }

        foreach ($cartIds as $cartId) {
            $cart = CartModel::findOne($cartId);
            if (!$cart instanceof CartModel) {
                continue;
            }

            try {
                $this->recalculateCart($cart);
                $stats['recalculatedCarts']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function recalculateCart(CartModel $cart): void
    {
        $items = CartItemModel::find()
            ->where(['cart_id' => (int)$cart->id])
            ->all();

        $itemsCount = 0;
        $subtotalAmount = 0.0;

        /** @var CartItemModel $item */
        foreach ($items as $item) {
            $itemsCount += (int)$item->quantity;
            $subtotalAmount += (float)$item->subtotal;
        }

        $cart->items_count = $itemsCount;
        $cart->subtotal_amount = $subtotalAmount;
        $cart->total_amount = $subtotalAmount;
        $cart->last_activity_at = time();

        if (!$cart->save()) {
            throw new \RuntimeException(
                'Failed to recalculate cart #' . $cart->id . ': ' . Json::encode($cart->errors)
            );
        }
    }
}