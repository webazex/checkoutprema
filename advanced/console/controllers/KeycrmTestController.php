<?php

namespace console\controllers;

use common\models\customer\CustomerModel;
use common\models\order\OrderModel;
use common\models\payment\PaymentModel;
use common\services\keycrm\KeyCrmCustomerSyncService;
use common\services\keycrm\KeyCrmOrderExportService;
use common\services\order\OrderPostPaymentProcessor;
use common\services\payment\PaymentService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use Throwable;

class KeycrmTestController extends Controller
{
    /**
     * Показывает краткую справку.
     */
    public function actionIndex(): int
    {
        $this->stdout("Available actions:\n", Console::FG_CYAN);
        $this->stdout("  yii keycrm-test/customer-sync <customerId>\n");
        $this->stdout("  yii keycrm-test/order-export <orderId>\n");
        $this->stdout("  yii keycrm-test/post-payment <orderId>\n");
        $this->stdout("  yii keycrm-test/callback-paid <paymentId>\n");
        $this->stdout("  yii keycrm-test/idempotency <orderId>\n");

        return ExitCode::OK;
    }

    /**
     * Прогоняет isolated customer sync:
     * local customer -> KeyCRM buyer -> save keycrm_customer_id
     */
    public function actionCustomerSync(int $customerId): int
    {
        try {
            $customer = CustomerModel::findOne($customerId);

            if (!$customer instanceof CustomerModel) {
                $this->stderr("Customer #{$customerId} not found.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $before = $customer->keycrm_customer_id;

            /** @var KeyCrmCustomerSyncService $service */
            $service = Yii::$container->get(KeyCrmCustomerSyncService::class);
            $service->sync($customer);

            $customer->refresh();

            $this->stdout("Customer sync completed.\n", Console::FG_GREEN);
            $this->stdout("Customer ID: {$customer->id}\n");
            $this->stdout("Email: {$customer->email}\n");
            $this->stdout("Phone: " . ($customer->phone ?: '-') . "\n");
            $this->stdout("KeyCRM customer ID before: " . ($before ?: 'null') . "\n");
            $this->stdout("KeyCRM customer ID after: " . ($customer->keycrm_customer_id ?: 'null') . "\n");

            return ExitCode::OK;
        } catch (Throwable $e) {
            return $this->renderThrowable($e);
        }
    }

    /**
     * Прогоняет isolated order export:
     * paid local order -> KeyCRM order -> save keycrm_order_id
     *
     * Важно: order должен быть уже paid и иметь customer + items.
     */
    public function actionOrderExport(int $orderId): int
    {
        try {
            $order = $this->loadOrder($orderId);
            $before = $order->keycrm_order_id;

            /** @var KeyCrmOrderExportService $service */
            $service = Yii::$container->get(KeyCrmOrderExportService::class);
            $service->export($order);

            $order->refresh();

            $this->stdout("Order export completed.\n", Console::FG_GREEN);
            $this->printOrderSummary($order, $before);

            return ExitCode::OK;
        } catch (Throwable $e) {
            return $this->renderThrowable($e);
        }
    }

    /**
     * Прогоняет полный post-payment processor:
     * paid order -> customer sync -> order export
     */
    public function actionPostPayment(int $orderId): int
    {
        try {
            $order = $this->loadOrder($orderId);

            $beforeCustomerKeycrmId = $order->customer?->keycrm_customer_id;
            $beforeOrderKeycrmId = $order->keycrm_order_id;

            /** @var OrderPostPaymentProcessor $processor */
            $processor = Yii::$container->get(OrderPostPaymentProcessor::class);
            $processor->process($order);

            $order->refresh();
            $order->populateRelation('customer', $order->getCustomer()->one());

            $this->stdout("Post-payment processing completed.\n", Console::FG_GREEN);
            $this->stdout(
                "Customer keycrm_customer_id: "
                . ($beforeCustomerKeycrmId ?: 'null')
                . " -> "
                . ($order->customer?->keycrm_customer_id ?: 'null')
                . "\n"
            );
            $this->stdout(
                "Order keycrm_order_id: "
                . ($beforeOrderKeycrmId ?: 'null')
                . " -> "
                . ($order->keycrm_order_id ?: 'null')
                . "\n"
            );

            return ExitCode::OK;
        } catch (Throwable $e) {
            return $this->renderThrowable($e);
        }
    }

    /**
     * Имитирует успешный callback path через PaymentService::handleCallback().
     *
     * ВАЖНО:
     * - payload ниже заточен под текущую WayForPay-логику лишь как тестовый шаблон;
     * - если твой parseCallback() требует дополнительные поля/подпись, дополни payload
     *   под фактический контракт gateway parser-а.
     */
    public function actionCallbackPaid(int $paymentId): int
    {
        try {
            $payment = PaymentModel::findOne($paymentId);

            if (!$payment instanceof PaymentModel) {
                $this->stderr("Payment #{$paymentId} not found.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if (empty($payment->external_order_id)) {
                $this->stderr(
                    "Payment #{$paymentId} has empty external_order_id. "
                    . "Run real payment init first, otherwise callback lookup will fail.\n",
                    Console::FG_RED
                );
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $payload = [
                // Ниже только каркас. Подстрой под реальный parseCallback() gateway.
                'merchantAccount' => Yii::$app->params['wayforpay.merchantAccount'] ?? '',
                'orderReference' => (string)$payment->external_order_id,
                'transactionStatus' => 'Approved',
                'reasonCode' => 1100,
                'orderStatus' => 'Approved',
                'amount' => (float)$payment->amount,
                'currency' => (string)$payment->currency,
            ];

            /** @var PaymentService $service */
            $service = Yii::$container->get(PaymentService::class);
            $result = $service->handleCallback($payment->provider, $payload);

            $payment->refresh();
            $order = $payment->order;
            if ($order instanceof OrderModel) {
                $order->refresh();
                $order->populateRelation('customer', $order->getCustomer()->one());
            }

            $this->stdout("Callback handled.\n", Console::FG_GREEN);
            $this->stdout("Parsed result status: {$result->status}\n");
            $this->stdout("Payment status: {$payment->status}\n");

            if ($order instanceof OrderModel) {
                $this->stdout("Order payment_status: {$order->payment_status}\n");
                $this->stdout("Order keycrm_order_id: " . ($order->keycrm_order_id ?: 'null') . "\n");
                $this->stdout(
                    "Customer keycrm_customer_id: "
                    . ($order->customer?->keycrm_customer_id ?: 'null')
                    . "\n"
                );
            }

            return ExitCode::OK;
        } catch (Throwable $e) {
            return $this->renderThrowable($e);
        }
    }

    /**
     * Проверка идемпотентности:
     * дважды гоняем process(order) и смотрим, не меняются ли keycrm IDs.
     */
    public function actionIdempotency(int $orderId): int
    {
        try {
            $order = $this->loadOrder($orderId);

            /** @var OrderPostPaymentProcessor $processor */
            $processor = Yii::$container->get(OrderPostPaymentProcessor::class);

            $processor->process($order);
            $order->refresh();
            $order->populateRelation('customer', $order->getCustomer()->one());

            $firstCustomerId = $order->customer?->keycrm_customer_id;
            $firstOrderId = $order->keycrm_order_id;

            $processor->process($order);
            $order->refresh();
            $order->populateRelation('customer', $order->getCustomer()->one());

            $secondCustomerId = $order->customer?->keycrm_customer_id;
            $secondOrderId = $order->keycrm_order_id;

            $this->stdout("Idempotency check completed.\n", Console::FG_GREEN);
            $this->stdout("Customer keycrm_customer_id: {$firstCustomerId} -> {$secondCustomerId}\n");
            $this->stdout("Order keycrm_order_id: {$firstOrderId} -> {$secondOrderId}\n");

            if ((string)$firstCustomerId !== (string)$secondCustomerId) {
                $this->stderr("Customer KeyCRM ID changed between runs.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if ((string)$firstOrderId !== (string)$secondOrderId) {
                $this->stderr("Order KeyCRM ID changed between runs.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            return ExitCode::OK;
        } catch (Throwable $e) {
            return $this->renderThrowable($e);
        }
    }

    private function loadOrder(int $orderId): OrderModel
    {
        $order = OrderModel::findOne($orderId);

        if (!$order instanceof OrderModel) {
            throw new \RuntimeException("Order #{$orderId} not found.");
        }

        $order->populateRelation('customer', $order->getCustomer()->one());
        $order->populateRelation('items', $order->getItems()->all());

        return $order;
    }

    private function printOrderSummary(OrderModel $order, mixed $beforeKeycrmOrderId = null): void
    {
        $this->stdout("Order ID: {$order->id}\n");
        $this->stdout("Hash: {$order->hash}\n");
        $this->stdout("Status: {$order->status}\n");
        $this->stdout("Payment status: {$order->payment_status}\n");
        $this->stdout("Customer ID: {$order->customer_id}\n");
        $this->stdout("Items count: " . count($order->items) . "\n");
        $this->stdout("KeyCRM order ID before: " . ($beforeKeycrmOrderId ?: 'null') . "\n");
        $this->stdout("KeyCRM order ID after: " . ($order->keycrm_order_id ?: 'null') . "\n");
    }

    private function renderThrowable(Throwable $e): int
    {
        $this->stderr("ERROR: " . $e->getMessage() . "\n", Console::FG_RED);
        $this->stderr("FILE: " . $e->getFile() . ":" . $e->getLine() . "\n", Console::FG_YELLOW);
        $this->stderr($e->getTraceAsString() . "\n", Console::FG_GREY);

        return ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionFindPaidOrder(): int
    {
        $orders = OrderModel::find()
            ->where(['payment_status' => OrderModel::PAYMENT_STATUS_PAID])
            ->andWhere(['or', ['keycrm_order_id' => null], ['keycrm_order_id' => '']])
            ->with(['customer', 'items'])
            ->orderBy(['id' => SORT_DESC])
            ->limit(10)
            ->all();

        foreach ($orders as $order) {
            $this->stdout(sprintf(
                "#%d | customer=%s | email=%s | phone=%s | items=%d | keycrm_order_id=%s\n",
                $order->id,
                $order->customer_id ?? 'null',
                $order->customer->email ?? '-',
                $order->customer->phone ?? '-',
                count($order->items),
                $order->keycrm_order_id ?: 'null'
            ));
        }

        return ExitCode::OK;
    }
}