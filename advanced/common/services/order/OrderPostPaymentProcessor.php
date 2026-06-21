<?php

declare(strict_types=1);

namespace common\services\order;

use common\models\order\OrderModel;
use common\services\keycrm\KeyCrmCustomerSyncService;
use common\services\keycrm\KeyCrmOrderExportService;
use DomainException;
use Throwable;
use Yii;

final class OrderPostPaymentProcessor
{
    public function __construct(
        private readonly KeyCrmCustomerSyncService $customerSyncService,
        private readonly KeyCrmOrderExportService  $orderExportService,
    )
    {
    }

    public function process(OrderModel $order): OrderModel
    {
        if (!$order->id) {
            throw new DomainException('Order must be saved before post-payment processing.');
        }

        $this->assertReadyForExport($order);

        $order->populateRelation('customer', $order->customer ?: $order->getCustomer()->one());
        $order->populateRelation('items', $order->items ?: $order->getItems()->all());
        if (!$order->customer) {
            throw new DomainException('Order customer is required for post-payment processing.');
        }

        try {
            $this->customerSyncService->sync($order->customer);
        } catch (Throwable $e) {
            Yii::warning([
                'message' => 'KeyCRM customer sync failed, continuing with order export.',
                'orderId' => (int)$order->id,
                'customerId' => (int)$order->customer->id,
                'exception' => $e->getMessage(),
            ], __METHOD__);
        }

        return $this->orderExportService->export($order);
    }

    private function assertReadyForExport(OrderModel $order): void
    {
        if ($order->payment_status !== OrderModel::PAYMENT_STATUS_PAID) {
            throw new DomainException(sprintf(
                'Order #%d is not paid. Current payment_status: %s',
                (int)$order->id,
                (string)$order->payment_status
            ));
        }

        if ($order->status === OrderModel::STATUS_CANCELLED) {
            throw new DomainException(sprintf(
                'Order #%d is cancelled and cannot be exported.',
                (int)$order->id
            ));
        }
    }
}