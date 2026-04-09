<?php

declare(strict_types=1);

namespace common\mappers\cart;

use common\dto\cart\CheckoutCartDto;
use common\dto\cart\CheckoutCartItemDto;
use common\models\cart\CartItemModel;
use common\models\cart\CartModel;

final class CheckoutCartMapper
{
    public function map(CartModel $cart): CheckoutCartDto
    {
        $items = [];

        foreach ($cart->items as $item) {
            if (!$item instanceof CartItemModel) {
                continue;
            }

            $items[] = new CheckoutCartItemDto(
                id: (int)$item->id,
                productId: $item->product_id ? (int)$item->product_id : null,
                title: (string)$item->title,
                sku: $item->sku_snapshot ?: null,
                price: (float)$item->price,
                quantity: (int)$item->quantity,
                subtotal: (float)$item->subtotal,
                currency: (string)$item->currency,
            );
        }

        return new CheckoutCartDto(
            id: (int)$cart->id,
            hash: (string)$cart->hash,
            sessionKey: (string)$cart->session_key,
            status: (string)$cart->status,
            sourceType: (string)$cart->source_type,
            currency: (string)$cart->currency,
            itemsCount: (int)$cart->items_count,
            subtotalAmount: (float)$cart->subtotal_amount,
            totalAmount: (float)$cart->total_amount,
            items: $items,
        );
    }
}