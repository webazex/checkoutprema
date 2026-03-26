<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use frontend\services\WixPayloadExtractor;
use frontend\services\CartEntryResolver;

class EntryController extends Controller
{
    /**
     * Входная точка storefront.
     *
     * Алгоритм:
     * 1. Пробуем извлечь входящие данные (Wix payload / query / body / etc.)
     * 2. Резолвим customer + draft order
     * 3. Редиректим в защищённую корзину
     */
    public function actionIndex(): Response
    {
        $payload = (new WixPayloadExtractor())->extract(Yii::$app->request);

        $result = (new CartEntryResolver())->resolve($payload);

        return $this->redirect([
            '/cart/view',
            'customerHash' => $result->customerHash,
            'orderId' => $result->orderId,
        ]);
    }
}