<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\product\ProductModel;
use DomainException;
use Yii;

final class CheckoutCartManageService
{
    public function clearByHash(string $cartHash): void
    {
        $cart = $this->findActiveCartByHash($cartHash);

        Yii::$app->db->transaction(function () use ($cart): void {
            CartItemModel::deleteAll(['cart_id' => (int)$cart->id]);

            $this->refreshCartTotals($cart);
        });
    }

    private function findActiveCartByHash(string $cartHash): CartModel
    {
        $cart = CartModel::find()
            ->where([
                'hash' => $cartHash,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->with('items.product')
            ->one();

        if (!$cart instanceof CartModel) {
            throw new DomainException('Active checkout cart was not found.');
        }

        return $cart;
    }

    private function refreshCartTotals(CartModel $cart): void
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
            throw new DomainException('Failed to update cart totals.');
        }
    }

    public function removeItemByHashAndItemId(string $cartHash, int $itemId): void
    {
        $cart = $this->findActiveCartByHash($cartHash);

        Yii::$app->db->transaction(function () use ($cart, $itemId): void {
            $item = CartItemModel::find()
                ->where([
                    'id' => $itemId,
                    'cart_id' => (int)$cart->id,
                ])
                ->one();

            if (!$item instanceof CartItemModel) {
                throw new DomainException('Cart item was not found.');
            }

            if ($item->delete() === false) {
                throw new DomainException('Failed to delete cart item.');
            }

            $this->refreshCartTotals($cart);
        });
    }

    public function normalizeStockByHash(string $cartHash): array
    {
        $cart = $this->findActiveCartByHash($cartHash);

        return Yii::$app->db->transaction(function () use ($cart): array {
            $changes = [];

            /** @var CartItemModel $item */
            foreach ($cart->items as $item) {
                if (!$item instanceof CartItemModel) {
                    continue;
                }

                $product = $item->product;

                if (!$product instanceof ProductModel || $product->getIsArchived()) {
                    $changes[] = [
                        'itemId' => (int)$item->id,
                        'productId' => $item->product_id ? (int)$item->product_id : null,
                        'title' => (string)$item->title,
                        'oldQuantity' => (int)$item->quantity,
                        'newQuantity' => 0,
                        'availableQuantity' => 0,
                        'action' => 'removed',
                        'reason' => 'product_unavailable',
                    ];

                    if ($item->delete() === false) {
                        throw new DomainException('Failed to delete unavailable cart item.');
                    }

                    continue;
                }

                $availableQuantity = max((int)$product->quantity, 0);
                $oldQuantity = (int)$item->quantity;

                if ($availableQuantity <= 0) {
                    $changes[] = [
                        'itemId' => (int)$item->id,
                        'productId' => $item->product_id ? (int)$item->product_id : null,
                        'title' => (string)$item->title,
                        'oldQuantity' => $oldQuantity,
                        'newQuantity' => 0,
                        'availableQuantity' => 0,
                        'action' => 'removed',
                        'reason' => 'out_of_stock',
                    ];

                    if ($item->delete() === false) {
                        throw new DomainException('Failed to delete out-of-stock cart item.');
                    }

                    continue;
                }

                $newQuantity = $oldQuantity;

                if ($newQuantity < 1) {
                    $newQuantity = 1;
                }

                if ($newQuantity > $availableQuantity) {
                    $newQuantity = $availableQuantity;
                }

                if ($newQuantity !== $oldQuantity) {
                    $item->quantity = $newQuantity;
                    $item->subtotal = round((float)$item->price * $newQuantity, 2);

                    if (!$item->save()) {
                        throw new DomainException('Failed to update cart item quantity.');
                    }

                    $changes[] = [
                        'itemId' => (int)$item->id,
                        'productId' => $item->product_id ? (int)$item->product_id : null,
                        'title' => (string)$item->title,
                        'oldQuantity' => $oldQuantity,
                        'newQuantity' => $newQuantity,
                        'availableQuantity' => $availableQuantity,
                        'action' => 'quantity_changed',
                        'reason' => 'stock_limit',
                    ];
                }
            }

            if ($changes !== []) {
                $this->refreshCartTotals($cart);
            }

            return $changes;
        });
    }

    public function updateItemQuantityByHashAndItemId(string $cartHash, int $itemId, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Invalid quantity.');
        }

        $cart = $this->findActiveCartByHash($cartHash);

        Yii::$app->db->transaction(function () use ($cart, $itemId, $quantity): void {
            /** @var CartItemModel|null $item */
            $item = CartItemModel::find()
                ->where([
                    'id' => $itemId,
                    'cart_id' => (int)$cart->id,
                ])
                ->with('product')
                ->one();

            if (!$item instanceof CartItemModel) {
                throw new DomainException('Cart item was not found.');
            }

            if (!$item->product instanceof ProductModel) {
                throw new DomainException('Product was not found.');
            }

            if ($item->product->getIsArchived()) {
                throw new DomainException('Product is no longer available.');
            }

            $availableQuantity = (int)$item->product->quantity;

            if ($availableQuantity <= 0) {
                throw new DomainException('Product is out of stock.');
            }

            if ($quantity > $availableQuantity) {
                throw new DomainException(sprintf(
                    'Only %d item(s) available.',
                    $availableQuantity
                ));
            }

            $item->quantity = $quantity;
            $item->subtotal = round((float)$item->price * $quantity, 2);

            if (!$item->save()) {
                throw new DomainException('Failed to update cart item.');
            }

            $this->refreshCartTotals($cart);
        });
    }
}