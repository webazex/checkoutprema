<?php

declare(strict_types=1);

namespace common\services\cart;

use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\product\ProductModel;
use RuntimeException;
use yii\db\Query;
use yii\helpers\Json;

final class BrokenCartItemCleanupService
{
    public function cleanupBrokenItems(): array
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
            ->leftJoin(['p' => ProductModel::tableName()], 'p.id = ci.product_id')
            ->where(['p.id' => null])
            ->all();

        if ($brokenRows === []) {
            return $stats;
        }

        $stats['brokenItemsFound'] = count($brokenRows);

        $brokenItemIds = [];
        $cartIds = [];

        foreach ($brokenRows as $row) {
            $brokenItemIds[] = (int) $row['id'];
            $cartIds[] = (int) $row['cart_id'];
        }

        $cartIds = array_values(array_unique(array_filter($cartIds)));
        $stats['affectedCarts'] = count($cartIds);

        if ($brokenItemIds !== []) {
            $deleted = CartItemModel::deleteAll(['id' => $brokenItemIds]);
            $stats['brokenItemsDeleted'] = (int) $deleted;
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

    public function cleanupEmptyActiveCarts(): array
    {
        $stats = [
            'scanned' => 0,
            'fixed' => 0,
            'alreadyClean' => 0,
            'errors' => 0,
        ];

        /** @var CartModel[] $carts */
        $carts = CartModel::find()
            ->where(['status' => CartModel::STATUS_ACTIVE])
            ->with('items')
            ->all();

        $stats['scanned'] = count($carts);

        foreach ($carts as $cart) {
            try {
                $itemsCount = count($cart->items);

                if ($itemsCount > 0) {
                    $stats['alreadyClean']++;
                    continue;
                }

                $needsFix =
                    (int) $cart->items_count !== 0 ||
                    (float) $cart->subtotal_amount !== 0.0 ||
                    (float) $cart->total_amount !== 0.0;

                if (!$needsFix) {
                    $stats['alreadyClean']++;
                    continue;
                }

                $cart->items_count = 0;
                $cart->subtotal_amount = 0;
                $cart->total_amount = 0;
                $cart->last_activity_at = time();

                if (!$cart->save()) {
                    throw new RuntimeException(
                        'Failed to cleanup empty cart #' . $cart->id . ': ' . Json::encode($cart->errors)
                    );
                }

                $stats['fixed']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function recalculateCart(CartModel $cart): void
    {
        $items = CartItemModel::find()
            ->where(['cart_id' => (int) $cart->id])
            ->all();

        $itemsCount = 0;
        $subtotalAmount = 0.0;

        /** @var CartItemModel $item */
        foreach ($items as $item) {
            $itemsCount += (int) $item->quantity;
            $subtotalAmount += (float) $item->subtotal;
        }

        $cart->items_count = $itemsCount;
        $cart->subtotal_amount = $subtotalAmount;
        $cart->total_amount = $subtotalAmount;
        $cart->last_activity_at = time();

        if (!$cart->save()) {
            throw new RuntimeException(
                'Failed to recalculate cart #' . $cart->id . ': ' . Json::encode($cart->errors)
            );
        }
    }

    public function cleanupAbandonedActiveCarts(int $olderThanDays = 7): array
    {
        $days = $olderThanDays > 0 ? $olderThanDays : 7;
        $threshold = time() - ($days * 24 * 60 * 60);

        $stats = [
            'scanned' => 0,
            'deleted' => 0,
            'skippedWithItems' => 0,
            'skippedFresh' => 0,
            'errors' => 0,
        ];

        /** @var CartModel[] $carts */
        $carts = CartModel::find()
            ->where(['status' => CartModel::STATUS_ACTIVE])
            ->with('items')
            ->all();

        $stats['scanned'] = count($carts);

        foreach ($carts as $cart) {
            try {
                if (!empty($cart->items)) {
                    $stats['skippedWithItems']++;
                    continue;
                }

                $lastActivityAt = (int)($cart->last_activity_at ?? 0);
                if ($lastActivityAt <= 0 || $lastActivityAt > $threshold) {
                    $stats['skippedFresh']++;
                    continue;
                }

                if ($cart->delete() === false) {
                    throw new RuntimeException(
                        'Failed to delete abandoned cart #' . $cart->id
                    );
                }

                $stats['deleted']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }
}