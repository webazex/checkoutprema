<?php

namespace common\services\payment;

use common\dto\payment\PaymentCallbackResponseDto;
use common\dto\payment\PaymentCallbackResultDto;
use common\dto\payment\PaymentCreateRequestDto;
use common\dto\payment\PaymentCreateResultDto;
use common\dto\payment\PaymentLineItemDto;
use common\dto\payment\PaymentNextActionDto;
use common\models\order\OrderModel;
use common\models\payment\PaymentLogModel;
use common\models\payment\PaymentModel;
use RuntimeException;
use common\services\order\OrderPostPaymentProcessor;
use Yii;

final class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gatewayRegistry,
    ) {
    }

    public function buildCreateRequest(
        PaymentModel $payment,
        string $returnUrl,
        string $callbackUrl
    ): PaymentCreateRequestDto {
        $order = $payment->order;

        if (!$order instanceof OrderModel) {
            throw new RuntimeException("Payment #{$payment->id} is not linked to an order.");
        }

        $items = [];

        foreach ($order->items as $item) {
            $items[] = new PaymentLineItemDto(
                name: (string)$item->title,
                quantity: (int)$item->quantity,
                price: (float)$item->price,
                sku: $item->sku_snapshot ?: null,
            );
        }

        if ($items === []) {
            throw new RuntimeException("Order #{$order->id} does not contain any items for payment.");
        }

        $orderReference = $payment->external_order_id ?: sprintf('payment-%d', (int)$payment->id);

        return new PaymentCreateRequestDto(
            paymentId: (int)$payment->id,
            orderId: (int)$payment->order_id,
            provider: (string)$payment->provider,
            orderReference: $orderReference,
            orderDate: $payment->created_at > 0 ? (int)$payment->created_at : time(),
            amount: (float)$payment->amount,
            currency: (string)$payment->currency,
            description: $this->buildDescription($order),
            items: $items,
            returnUrl: $returnUrl,
            callbackUrl: $callbackUrl,
            customerEmail: $order->customer_email ?: null,
            customerPhone: $order->customer_phone ?: null,
            customerFirstName: $order->customer_first_name ?: null,
            customerLastName: $order->customer_last_name ?: null,
            metadata: [
                'orderHash' => $order->hash,
                'paymentId' => $payment->id,
                'orderId' => $order->id,
            ],
        );
    }

    public function createPayment(
        PaymentModel $payment,
        PaymentCreateRequestDto $request
    ): PaymentCreateResultDto {
        $this->assertProviderConsistency($payment, $request->provider);

        $gateway = $this->gatewayRegistry->get($payment->provider);
        $result = $gateway->createPayment($request);
        $now = time();

        PaymentModel::getDb()->transaction(function () use ($payment, $request, $result, $now): void {
            $payment->status = $result->status;
            $payment->external_id = $result->externalId;
            $payment->external_order_id = $result->externalOrderId ?? $request->orderReference;
            $payment->redirect_url = $result->redirectUrl
                ?? ($result->nextAction?->isRedirect() ? $result->nextAction->url : null);
            $payment->error_message = $result->errorMessage;
            $payment->updated_at = $now;

            $this->applyPaymentTerminalTimestamps($payment, $result->status, $now);

            if (!$payment->save(false)) {
                throw new RuntimeException("Failed to save payment #{$payment->id} after createPayment().");
            }

            $this->syncOrderPaymentState($payment, $now);

            $this->writeLog(
                payment: $payment,
                eventType: PaymentLogModel::EVENT_REQUEST,
                direction: PaymentLogModel::DIRECTION_OUT,
                status: $result->status,
                payload: [
                    'request' => $this->serializeCreateRequest($request),
                    'result' => $this->serializeCreateResult($result),
                ],
                errorMessage: $result->errorMessage,
                externalId: $result->externalId,
            );
        });

        return $result;
    }

    public function handleCallback(
        string $provider,
        array $payload
    ): PaymentCallbackResultDto {
        $gateway = $this->gatewayRegistry->get($provider);
        $result = $gateway->parseCallback($payload);

        if ($result->provider !== $provider) {
            throw new RuntimeException(
                "Callback provider mismatch. Expected {$provider}, got {$result->provider}."
            );
        }

        $payment = $this->findPaymentForCallback($provider, $result);

        if (!$payment instanceof PaymentModel) {
            $externalOrderId = $result->externalOrderId ?? 'null';
            $externalId = $result->externalId ?? 'null';

            throw new RuntimeException(
                "Payment not found for callback. provider={$provider}, externalOrderId={$externalOrderId}, externalId={$externalId}"
            );
        }

        $now = time();

        PaymentModel::getDb()->transaction(function () use ($payment, $result, $now): void {
            $this->writeLog(
                payment: $payment,
                eventType: PaymentLogModel::EVENT_CALLBACK,
                direction: PaymentLogModel::DIRECTION_IN,
                status: $result->status,
                payload: $this->serializeCallbackResult($result),
                errorMessage: $result->errorMessage,
                externalId: $result->externalId,
            );

            if (!$result->isValid) {
                $payment->error_message = $result->errorMessage ?: 'Invalid callback signature.';
                $payment->updated_at = $now;
                $payment->save(false);

                $this->writeLog(
                    payment: $payment,
                    eventType: PaymentLogModel::EVENT_ERROR,
                    direction: PaymentLogModel::DIRECTION_INTERNAL,
                    status: $payment->status,
                    payload: [
                        'reason' => 'invalid_callback_signature',
                        'callback' => $this->serializeCallbackResult($result),
                    ],
                    errorMessage: $payment->error_message,
                    externalId: $result->externalId,
                );

                return;
            }

            $oldStatus = $payment->status;

            if ($result->externalId !== null && $result->externalId !== '') {
                $payment->external_id = $result->externalId;
            }

            if ($result->externalOrderId !== null && $result->externalOrderId !== '') {
                $payment->external_order_id = $result->externalOrderId;
            }

            if ($result->paymentMethod !== null && $result->paymentMethod !== '') {
                $payment->payment_method = $result->paymentMethod;
            }

            $payment->status = $result->status;
            $payment->error_message = $result->errorMessage;
            $payment->updated_at = $now;

            $this->applyPaymentTerminalTimestamps($payment, $result->status, $now);

            if (!$payment->save(false)) {
                throw new RuntimeException("Failed to save payment #{$payment->id} after callback.");
            }

            $this->syncOrderPaymentState($payment, $now);
            $this->processSuccessfulOrderExport($payment);

            if ($oldStatus !== $payment->status) {
                $this->writeLog(
                    payment: $payment,
                    eventType: PaymentLogModel::EVENT_STATUS_CHANGE,
                    direction: PaymentLogModel::DIRECTION_INTERNAL,
                    status: $payment->status,
                    payload: [
                        'from' => $oldStatus,
                        'to' => $payment->status,
                        'callback' => $this->serializeCallbackResult($result),
                    ],
                    errorMessage: $result->errorMessage,
                    externalId: $result->externalId,
                );
            }
        });

        return $result;
    }

    public function buildCallbackResponse(
        string $provider,
        PaymentCallbackResultDto $result
    ): PaymentCallbackResponseDto {
        return $this->gatewayRegistry
            ->get($provider)
            ->buildCallbackResponse($result);
    }

    private function assertProviderConsistency(PaymentModel $payment, string $provider): void
    {
        if ($payment->provider !== $provider) {
            throw new RuntimeException(
                "Payment provider mismatch. Payment #{$payment->id} has provider {$payment->provider}, request provider is {$provider}."
            );
        }
    }

    private function findPaymentForCallback(
        string $provider,
        PaymentCallbackResultDto $result
    ): ?PaymentModel {
        if ($result->externalOrderId !== null && $result->externalOrderId !== '') {
            $payment = PaymentModel::find()
                ->provider($provider)
                ->byExternalOrderId($result->externalOrderId)
                ->one();

            if ($payment instanceof PaymentModel) {
                return $payment;
            }
        }

        if ($result->externalId !== null && $result->externalId !== '') {
            $payment = PaymentModel::find()
                ->provider($provider)
                ->byExternalId($result->externalId)
                ->one();

            if ($payment instanceof PaymentModel) {
                return $payment;
            }
        }

        return null;
    }

    private function applyPaymentTerminalTimestamps(
        PaymentModel $payment,
        string $status,
        int $now
    ): void {
        if ($status === PaymentModel::STATUS_PAID && empty($payment->paid_at)) {
            $payment->paid_at = $now;
        }

        if (
            in_array($status, [
                PaymentModel::STATUS_FAILED,
                PaymentModel::STATUS_CANCELLED,
            ], true)
            && empty($payment->failed_at)
        ) {
            $payment->failed_at = $now;
        }
    }

    private function syncOrderPaymentState(PaymentModel $payment, int $now): void
    {
        $order = $payment->order;

        if (!$order instanceof OrderModel) {
            return;
        }

        $order->payment_status = $payment->status;

        if (!empty($payment->payment_method)) {
            $order->payment_method = $payment->payment_method;
        }

        if ($payment->status === PaymentModel::STATUS_PAID && empty($order->paid_at)) {
            $order->paid_at = $payment->paid_at ?: $now;
        }

        $order->updated_at = $now;
        $order->save(false);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeLog(
        PaymentModel $payment,
        string $eventType,
        string $direction,
        string $status,
        array $payload = [],
        ?string $errorMessage = null,
        ?string $externalId = null
    ): void {
        $log = new PaymentLogModel();
        $log->order_id = $payment->order_id;
        $log->payment_id = $payment->id;
        $log->provider = $payment->provider;
        $log->event_type = $eventType;
        $log->direction = $direction;
        $log->external_id = $externalId;
        $log->status = $status;
        $log->amount = $payment->amount;
        $log->currency = $payment->currency;
        $log->payment_method = $payment->payment_method;
        $log->response_data = $this->encodeData($payload);
        $log->error_message = $errorMessage;
        $log->created_at = time();
        $log->updated_at = time();
        $log->save(false);
    }

    private function buildDescription(OrderModel $order): string
    {
        if (!empty($order->hash)) {
            return sprintf('Order #%s', $order->hash);
        }

        return sprintf('Order #%d', $order->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCreateRequest(PaymentCreateRequestDto $request): array
    {
        return [
            'paymentId' => $request->paymentId,
            'orderId' => $request->orderId,
            'provider' => $request->provider,
            'orderReference' => $request->orderReference,
            'orderDate' => $request->orderDate,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'description' => $request->description,
            'items' => array_map(
                static fn (PaymentLineItemDto $item): array => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'sku' => $item->sku,
                ],
                $request->items
            ),
            'returnUrl' => $request->returnUrl,
            'callbackUrl' => $request->callbackUrl,
            'customerEmail' => $request->customerEmail,
            'customerPhone' => $request->customerPhone,
            'customerFirstName' => $request->customerFirstName,
            'customerLastName' => $request->customerLastName,
            'metadata' => $request->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCreateResult(PaymentCreateResultDto $result): array
    {
        return [
            'provider' => $result->provider,
            'status' => $result->status,
            'externalId' => $result->externalId,
            'externalOrderId' => $result->externalOrderId,
            'redirectUrl' => $result->redirectUrl,
            'nextAction' => $this->serializeNextAction($result->nextAction),
            'errorMessage' => $result->errorMessage,
            'rawResponse' => $result->rawResponse,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCallbackResult(PaymentCallbackResultDto $result): array
    {
        return [
            'provider' => $result->provider,
            'isValid' => $result->isValid,
            'status' => $result->status,
            'externalId' => $result->externalId,
            'externalOrderId' => $result->externalOrderId,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'paymentMethod' => $result->paymentMethod,
            'providerStatus' => $result->providerStatus,
            'errorMessage' => $result->errorMessage,
            'rawPayload' => $result->rawPayload,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeNextAction(?PaymentNextActionDto $nextAction): ?array
    {
        if ($nextAction === null) {
            return null;
        }

        return [
            'type' => $nextAction->type,
            'url' => $nextAction->url,
            'method' => $nextAction->method,
            'payload' => $nextAction->payload,
        ];
    }

    private function encodeData(array $payload): ?string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }
    private function processSuccessfulOrderExport(PaymentModel $payment): void
    {
        if ($payment->status !== PaymentModel::STATUS_PAID) {
            return;
        }

        $order = $payment->order;

        if (!$order instanceof OrderModel) {
            throw new RuntimeException(sprintf(
                'Payment #%d is not linked to a valid order for post-payment processing.',
                (int)$payment->id
            ));
        }

        /** @var OrderPostPaymentProcessor $processor */
        $processor = Yii::$container->get(OrderPostPaymentProcessor::class);
        $processor->process($order);
    }
}