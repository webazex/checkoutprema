<?php

declare(strict_types=1);

namespace frontend\services\cart;

use common\models\cart\CartModel;
use Yii;
use yii\helpers\Url;

final class ActiveCartProvider
{
    public function getState(): array
    {
        $cart = $this->findActiveCart();
        $itemsCount = $cart instanceof CartModel ? max(0, (int)$cart->items_count) : 0;
        $hasItems = $cart instanceof CartModel && $itemsCount > 0;

        return [
            'cart' => $cart,
            'hasItems' => $hasItems,
            'itemsCount' => $itemsCount,
            'cartUrl' => $hasItems
                ? Url::to(['/checkout/view', 'hash' => (string)$cart->hash])
                : Url::to(['/cart/index']),
        ];
    }

    public function findActiveCart(): ?CartModel
    {
        $this->ensureSessionStarted();

        $session = Yii::$app->session;
        $activeCartHash = (string)$session->get('active_cart_hash', '');

        if ($activeCartHash !== '') {
            /** @var CartModel|null $cart */
            $cart = CartModel::find()
                ->where([
                    'hash' => $activeCartHash,
                    'status' => CartModel::STATUS_ACTIVE,
                ])
                ->one();

            if ($cart instanceof CartModel) {
                return $cart;
            }
        }

        $sessionKey = (string)$session->id;

        if ($sessionKey === '') {
            return null;
        }

        /** @var CartModel|null $cart */
        $cart = CartModel::find()
            ->where([
                'session_key' => $sessionKey,
                'source_type' => CartModel::SOURCE_DIRECT,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($cart instanceof CartModel) {
            $session->set('active_cart_hash', (string)$cart->hash);

            return $cart;
        }

        return null;
    }

    private function ensureSessionStarted(): void
    {
        if (!Yii::$app->session->isActive) {
            Yii::$app->session->open();
        }
    }
}