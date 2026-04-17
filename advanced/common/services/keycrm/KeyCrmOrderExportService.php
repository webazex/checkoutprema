<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\integrations\keycrm\KeyCrmApiClient;
use common\models\customer\CustomerModel;
use common\models\order\OrderItemModel;
use common\models\order\OrderModel;
use DomainException;
use RuntimeException;
use Yii;
use yii\helpers\ArrayHelper;

final class KeyCrmOrderExportService
{
    public function __construct(
        private readonly KeyCrmApiClient $apiClient,
    ) {
    }

    public function export(OrderModel $order): OrderModel
    {
        if (!$order->id) {
            throw new DomainException('Order must be saved locally before KeyCRM export.');
        }

        if ($this->hasKeyCrmId($order)) {
            return $order;
        }

        $order->populateRelation('customer', $order->customer ?: $order->getCustomer()->one());
        $order->populateRelation('items', $order->items ?: $order->getItems()->all());

        if (!$order->customer instanceof CustomerModel) {
            throw new DomainException('Order customer is required for KeyCRM export.');
        }

        if (empty($order->items)) {
            throw new DomainException('Order items are required for KeyCRM export.');
        }

        $payload = $this->buildPayload($order);
        $response = $this->apiClient->post('/order', $payload);

        if (!is_array($response)) {
            throw new RuntimeException('KeyCRM create order returned invalid response.');
        }

        $remoteId = $this->extractRemoteOrderId($response);

        if ($remoteId === null || $remoteId === '') {
            throw new RuntimeException('KeyCRM order ID was not returned.');
        }

        $order->keycrm_order_id = $remoteId;

        if (!$order->save(true, ['keycrm_order_id', 'updated_at'])) {
            throw new DomainException(
                'Failed to save keycrm_order_id for local order: ' .
                json_encode($order->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $order;
    }

    private function hasKeyCrmId(OrderModel $order): bool
    {
        return is_string($order->keycrm_order_id)
            && trim($order->keycrm_order_id) !== '';
    }

    private function buildPayload(OrderModel $order): array
    {
        $sourceId = (int)(Yii::$app->params['keycrm.orderSourceId'] ?? 0);

        if ($sourceId <= 0) {
            throw new DomainException('KeyCRM orderSourceId param must be configured.');
        }

        $buyer = $this->buildBuyer($order);
        $products = $this->buildProducts($order);
        $payments = $this->buildPayments($order);

        $payload = [
            'source_id' => $sourceId,
            'buyer' => $buyer,
            'products' => $products,
        ];

        if ($payments !== []) {
            $payload['payments'] = $payments;
        }

        return $payload;
    }

    private function buildBuyer(OrderModel $order): array
    {
        $fullName = trim(implode(' ', array_filter([
            $order->customer_first_name,
            $order->customer_last_name,
        ])));

        if ($fullName === '') {
            $fullName = $order->customer?->getFullName() ?: '';
        }

        $email = $this->nullableString($order->customer_email ?: $order->customer?->email);
        $phone = $this->nullableString($order->customer_phone ?: $order->customer?->phone);

        if ($fullName === '' && $email === null && $phone === null) {
            throw new DomainException('Order buyer must have full_name, email or phone.');
        }

        $buyer = [];

        if ($fullName !== '') {
            $buyer['full_name'] = $fullName;
        }

        if ($email !== null) {
            $buyer['email'] = $email;
        }

        if ($phone !== null) {
            $buyer['phone'] = $phone;
        }

        return $buyer;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProducts(OrderModel $order): array
    {
        $products = [];

        /** @var OrderItemModel $item */
        foreach ($order->items as $item) {
            $sku = $this->nullableString($item->sku_snapshot);
            $name = $this->nullableString($item->title);

            if ($sku === null && $name === null) {
                throw new DomainException(
                    sprintf('Order item #%d must have at least sku or name for KeyCRM export.', (int)$item->id)
                );
            }

            $row = [
                'price' => (float)$item->price,
                'quantity' => (int)$item->quantity,
            ];

            if ($sku !== null) {
                $row['sku'] = $sku;
            }

            if ($name !== null) {
                $row['name'] = $name;
            }

            $products[] = $row;
        }

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPayments(OrderModel $order): array
    {
        if ($order->payment_status !== OrderModel::PAYMENT_STATUS_PAID) {
            return [];
        }

        $paymentMethodId = Yii::$app->params['keycrm.paymentMethodId'] ?? null;
        $paymentMethodName = (string)(Yii::$app->params['keycrm.paymentMethodName'] ?? $order->payment_method ?? 'WayForPay');

        $payment = [
            'amount' => (float)$order->total_amount,
            'status' => 'paid',
            'description' => sprintf('Order %s paid on CheckoutPrema', (string)$order->hash),
        ];

        if ($paymentMethodId !== null && $paymentMethodId !== '') {
            $payment['payment_method_id'] = (int)$paymentMethodId;
        }

        if ($paymentMethodName !== '') {
            $payment['payment_method'] = $paymentMethodName;
        }

        if (!empty($order->paid_at)) {
            $payment['payment_date'] = date('Y-m-d H:i:s', (int)$order->paid_at);
        }

        return [$payment];
    }

    private function extractRemoteOrderId(array $response): ?string
    {
        $id = ArrayHelper::getValue($response, 'id')
            ?? ArrayHelper::getValue($response, 'data.id');

        if ($id === null) {
            return null;
        }

        $id = trim((string)$id);

        return $id !== '' ? $id : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}