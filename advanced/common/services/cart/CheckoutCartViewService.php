<?php

declare(strict_types=1);

namespace common\services\cart;

use common\dto\cart\CheckoutCartDto;
use common\mappers\cart\CheckoutCartMapper;
use common\models\cart\CartModel;
use yii\web\NotFoundHttpException;

final class CheckoutCartViewService
{
    public function __construct(
        private readonly CheckoutCartMapper $mapper,
    ) {
    }

    public function getActiveCartByHash(string $hash): CheckoutCartDto
    {
        $cart = CartModel::find()
            ->where([
                'hash' => $hash,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->with('items.product')
            ->one();

        if (!$cart instanceof CartModel) {
            throw new NotFoundHttpException('Checkout cart was not found.');
        }

        return $this->mapper->map($cart);
    }
}