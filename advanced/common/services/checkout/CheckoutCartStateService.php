<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\dto\cart\CheckoutCartDto;
use common\mappers\cart\CheckoutCartMapper;
use common\models\cart\CartModel;

final class CheckoutCartStateService
{
    public function __construct(
        private readonly CheckoutCartMapper $cartMapper,
    )
    {
    }

    public function getActiveCartBySession(string $sessionKey, string $sourceType): CheckoutCartDto
    {
        $cart = CartModel::find()
            ->where([
                'session_key' => $sessionKey,
                'source_type' => $sourceType,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->with(['items.product'])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($cart instanceof CartModel) {
            return $this->cartMapper->map($cart);
        }

        return $this->buildEmptyCartDto($sessionKey, $sourceType);
    }

    private function buildEmptyCartDto(string $sessionKey, string $sourceType): CheckoutCartDto
    {
        return new CheckoutCartDto(
            id: 0,
            hash: '',
            sessionKey: $sessionKey,
            status: CartModel::STATUS_ACTIVE,
            sourceType: $sourceType,
            currency: 'UAH',
            itemsCount: 0,
            subtotalAmount: 0.0,
            totalAmount: 0.0,
            items: [],
        );
    }
}