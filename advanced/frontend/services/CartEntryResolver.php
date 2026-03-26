<?php

namespace frontend\services;

use Yii;
use common\dto\WixCartPayloadDto;
use frontend\dto\CartEntryResultDto;
use common\models\Order;
use common\services\cart\DraftOrderService;
use common\services\cart\CartMergeService;
use common\services\customer\CustomerResolver;

/**
 * Оркестратор входного storefront-сценария.
 */
class CartEntryResolver
{
    public function resolve(WixCartPayloadDto $payload): CartEntryResultDto
    {
        $customerResolver = new CustomerResolver();
        $draftOrderService = new DraftOrderService();
        $cartMergeService = new CartMergeService();

        $customer = $customerResolver->resolveByPayload($payload);

        $order = $draftOrderService->findOrCreateDraftOrder($customer, $payload);

        $result = new CartEntryResultDto();
        $result->customerHash = $order->customer_hash;
        $result->orderId = (int)$order->id;
        $result->isKnownCustomer = $customer !== null;

        if ($payload->hasItems()) {
            $cartMergeService->mergeIncomingItemsIntoOrder($order, $payload->items);
            $result->usedIncomingItems = true;
        } else {
            // TODO:
            // здесь позже можно добавить поиск и восстановление брошенной корзины
            // например:
            // $restored = $draftOrderService->restoreAbandonedCart($customer, $payload);
            // if ($restored) { ... }
        }

        Yii::$app->session->set('customer_hash', $order->customer_hash);
        Yii::$app->session->set('active_order_id', $order->id);

        return $result;
    }
}