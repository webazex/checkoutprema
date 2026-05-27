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

    private function findActiveCartByHash(string $cartHash): CartModel
    {
        $cart = CartModel::find()
            ->where([
                'hash' => $cartHash,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->with('items')
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