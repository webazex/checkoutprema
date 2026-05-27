<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\models\checkout\CheckoutSubmitInput;
use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\meta\MetaModel;
use common\models\order\OrderItemModel;
use common\models\order\OrderModel;
use common\models\payment\PaymentModel;
use common\services\payment\PaymentService;
use DomainException;
use RuntimeException;
use Yii;
use yii\helpers\Json;
use common\models\product\ProductModel;

final class CheckoutSubmitService
{
    public function __construct(
        private readonly CheckoutCustomerResolver $customerResolver,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function submit(array $payload, string $callbackUrl, string $defaultReturnUrl): array
    {
        $input = new CheckoutSubmitInput();
        $input->load($payload, '');

        if (!$input->validate()) {
            throw new DomainException(
                'Checkout payload validation failed: ' .
                Json::encode($input->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        $returnUrl = $input->returnUrl ?: $defaultReturnUrl;
        $now = time();
        $result = Yii::$app->db->transaction(function () use ($input, $now): array {
            $cart = $this->resolveCart($input);

            if (!$cart instanceof CartModel) {
                throw new DomainException('Active cart was not found for the provided sessionKey/sourceType.');
            }

            if ($cart->items === [] || count($cart->items) === 0) {
                throw new DomainException('Cannot submit an empty cart.');
            }
            $this->validateCartStock($cart);
            $existingOrder = OrderModel::find()
                ->where(['cart_id' => $cart->id])
                ->one();

            if ($existingOrder instanceof OrderModel) {
                throw new DomainException(sprintf(
                    'This cart is already attached to order "%s".',
                    $existingOrder->hash
                ));
            }

            $customer = $this->customerResolver->resolve($input->getCustomerPayload());

            $order = new OrderModel();
            $order->hash = Yii::$app->security->generateRandomString(32);
            $order->customer_id = (int)$customer->id;
            $order->cart_id = (int)$cart->id;
            $order->total_amount = (float)$cart->total_amount;
            $order->currency = (string)$cart->currency;
            $order->subtotal_amount = (float)$cart->subtotal_amount;
            $order->discount_amount = 0.0;
            $order->shipping_amount = 0.0;
            $order->status = OrderModel::STATUS_NEW;
            $order->payment_status = PaymentModel::STATUS_NEW;
            $order->payment_method = $input->payment_method ?: PaymentModel::PROVIDER_WAYFORPAY;
            $order->customer_email = (string)$customer->email;
            $order->customer_phone = $customer->phone;
            $order->customer_first_name = $customer->first_name;
            $order->customer_last_name = $customer->last_name;
            $order->source_type = (string)$cart->source_type;
            $order->placed_at = $now;

            if (!$order->save()) {
                throw new DomainException(
                    'Failed to save order: ' . Json::encode($order->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }

            foreach ($cart->items as $cartItem) {
                if (!$cartItem instanceof CartItemModel) {
                    continue;
                }

                $orderItem = new OrderItemModel();
                $orderItem->order_id = (int)$order->id;
                $orderItem->product_id = $cartItem->product_id ? (int)$cartItem->product_id : null;
                $orderItem->wix_product_id = null;
                $orderItem->title = (string)$cartItem->title;
                $orderItem->sku_snapshot = $cartItem->sku_snapshot;
                $orderItem->price = (float)$cartItem->price;
                $orderItem->quantity = (int)$cartItem->quantity;
                $orderItem->subtotal = (float)$cartItem->subtotal;
                $orderItem->currency = (string)$cartItem->currency;
                $orderItem->product_payload_snapshot = Json::encode([
                    'cartItemId' => (int)$cartItem->id,
                    'productId' => $cartItem->product_id ? (int)$cartItem->product_id : null,
                    'title' => (string)$cartItem->title,
                    'sku' => $cartItem->sku_snapshot,
                    'price' => (float)$cartItem->price,
                    'quantity' => (int)$cartItem->quantity,
                    'subtotal' => (float)$cartItem->subtotal,
                    'currency' => (string)$cartItem->currency,
                    'sourceType' => (string)$cart->source_type,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if (!$orderItem->save()) {
                    throw new DomainException(
                        'Failed to save order item: ' . Json::encode($orderItem->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                    );
                }
            }

            $delivery = $input->getDeliveryPayload();
            MetaModel::upsertText(MetaModel::ENTITY_ORDER, (int)$order->id, 'delivery.region', $delivery['region'] ?? null);
            MetaModel::upsertText(MetaModel::ENTITY_ORDER, (int)$order->id, 'delivery.city', $delivery['city'] ?? null);
            MetaModel::upsertText(MetaModel::ENTITY_ORDER, (int)$order->id, 'delivery.branch', $delivery['branch'] ?? null);

            $payment = new PaymentModel();
            $payment->order_id = (int)$order->id;
            $payment->customer_id = (int)$customer->id;
            $payment->provider = PaymentModel::PROVIDER_WAYFORPAY;
            $payment->status = PaymentModel::STATUS_NEW;
            $payment->amount = (float)$order->total_amount;
            $payment->currency = (string)$order->currency;
            $payment->payment_method = $input->payment_method ?: PaymentModel::PROVIDER_WAYFORPAY;
            $payment->external_order_id = 'order-' . $order->hash;
            $payment->idempotency_key = 'order-' . $order->id . '-wayforpay';

            if (!$payment->save()) {
                throw new DomainException(
                    'Failed to save payment: ' . Json::encode($payment->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }

            $cart->status = CartModel::STATUS_CONVERTED;
            $cart->last_activity_at = $now;

            if (!$cart->save()) {
                throw new DomainException(
                    'Failed to finalize cart: ' . Json::encode($cart->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }

            return [
                'customer' => $customer,
                'cart' => $cart,
                'order' => $order,
                'payment' => $payment,
            ];
        });

        /** @var OrderModel $order */
        $order = $result['order'];
        /** @var PaymentModel $payment */
        $payment = $result['payment'];

        try {
            $createRequest = $this->paymentService->buildCreateRequest(
                $payment,
                $returnUrl,
                $callbackUrl,
            );

            $createResult = $this->paymentService->createPayment($payment, $createRequest);

            $order->refresh();
            $payment->refresh();

            return [
                'order' => [
                    'id' => (int)$order->id,
                    'hash' => (string)$order->hash,
                    'status' => (string)$order->status,
                    'paymentStatus' => (string)($order->payment_status ?? ''),
                    'currency' => (string)$order->currency,
                    'subtotalAmount' => (float)$order->subtotal_amount,
                    'shippingAmount' => (float)$order->shipping_amount,
                    'discountAmount' => (float)$order->discount_amount,
                    'totalAmount' => (float)$order->total_amount,
                    'paymentMethod' => (string)($order->payment_method ?? ''),
                ],
                'payment' => [
                    'id' => (int)$payment->id,
                    'provider' => (string)$payment->provider,
                    'status' => (string)$payment->status,
                    'amount' => (float)$payment->amount,
                    'currency' => (string)$payment->currency,
                    'paymentMethod' => (string)($payment->payment_method ?? ''),
                    'redirectUrl' => $createResult->redirectUrl,
                    'nextAction' => $createResult->nextAction ? [
                        'type' => $createResult->nextAction->type,
                        'url' => $createResult->nextAction->url,
                        'method' => $createResult->nextAction->method,
                        'payload' => $createResult->nextAction->payload,
                    ] : null,
                    'rawResponse' => $createResult->rawResponse,
                ],
            ];
        } catch (\Throwable $e) {
            throw new RuntimeException(
                sprintf(
                    'Order "%s" was created, but payment initialization failed: %s',
                    $order->hash,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    private function validateCartStock(CartModel $cart): void
    {
        foreach ($cart->items as $cartItem) {
            if (!$cartItem instanceof CartItemModel) {
                continue;
            }

            $product = $cartItem->product;

            if (!$product instanceof ProductModel) {
                throw new DomainException(sprintf(
                    'Product "%s" is no longer available.',
                    (string)$cartItem->title
                ));
            }

            if ($product->getIsArchived()) {
                throw new DomainException(sprintf(
                    'Product "%s" is no longer available.',
                    (string)$cartItem->title
                ));
            }

            $requestedQuantity = (int)$cartItem->quantity;
            $availableQuantity = (int)$product->quantity;

            if ($availableQuantity <= 0) {
                throw new DomainException(sprintf(
                    'Product "%s" is out of stock.',
                    (string)$cartItem->title
                ));
            }

            if ($requestedQuantity > $availableQuantity) {
                throw new DomainException(sprintf(
                    'Only %d item(s) of "%s" available.',
                    $availableQuantity,
                    (string)$cartItem->title
                ));
            }
        }
    }

    private function resolveCart(CheckoutSubmitInput $input): CartModel
    {
        $query = CartModel::find()
            ->with('items.product')
            ->andWhere(['status' => CartModel::STATUS_ACTIVE]);

        if (trim((string)$input->cartHash) !== '') {
            $query->andWhere(['hash' => $input->cartHash]);
        } else {
            $query->andWhere([
                'session_key' => $input->sessionKey,
                'source_type' => $input->sourceType,
            ]);
        }

        $cart = $query->one();

        if (!$cart instanceof CartModel) {
            throw new DomainException('Active cart was not found.');
        }

        return $cart;
    }
}