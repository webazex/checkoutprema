<?php


namespace common\services\payment;

use common\dto\payment\PaymentCreateRequestDto;
use common\models\payment\PaymentLogModel;
use common\models\payment\PaymentModel;

final class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gatewayRegistry,
    )
    {
    }

    public function createPayment(PaymentModel $payment, PaymentCreateRequestDto $request): void
    {
        $gateway = $this->gatewayRegistry->get($payment->provider);
        $result = $gateway->createPayment($request);

        $payment->status = $result->status;
        $payment->external_id = $result->externalId;
        $payment->external_order_id = $result->externalOrderId;
        $payment->redirect_url = $result->redirectUrl;
        $payment->error_message = $result->errorMessage;
        $payment->updated_at = time();
        $payment->save(false);

        $log = new PaymentLogModel();
        $log->order_id = $payment->order_id;
        $log->payment_id = $payment->id;
        $log->provider = $payment->provider;
        $log->event_type = PaymentLogModel::EVENT_REQUEST;
        $log->direction = PaymentLogModel::DIRECTION_OUT;
        $log->external_id = $result->externalId;
        $log->status = $result->status;
        $log->amount = $payment->amount;
        $log->currency = $payment->currency;
        $log->payment_method = $payment->payment_method;
        $log->response_data = json_encode($result->rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log->created_at = time();
        $log->updated_at = time();
        $log->save(false);
    }
}