<?php


namespace common\services\payment\gateways;

use common\contracts\payment\PaymentGatewayInterface;
use common\dto\payment\PaymentCreateRequestDto;
use common\dto\payment\PaymentCreateResultDto;
use common\dto\payment\PaymentCallbackResultDto;
use common\models\payment\PaymentModel;

final class WayForPayGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $merchantAccount,
        private readonly string $merchantDomainName,
        private readonly string $merchantSecretKey,
    )
    {
    }

    public function getCode(): string
    {
        return PaymentModel::PROVIDER_WAYFORPAY;
    }

    public function createPayment(PaymentCreateRequestDto $request): PaymentCreateResultDto
    {
        return new PaymentCreateResultDto(
            provider: $this->getCode(),
            status: PaymentModel::STATUS_PENDING,
            externalId: null,
            externalOrderId: (string)$request->orderId,
            redirectUrl: null,
            errorMessage: null,
            rawResponse: [],
        );
    }

    public function parseCallback(array $payload): PaymentCallbackResultDto
    {
        return new PaymentCallbackResultDto(
            provider: $this->getCode(),
            isValid: $this->validateCallback($payload),
            status: $this->mapStatus((string)($payload['transactionStatus'] ?? '')),
            externalId: $payload['reasonCode'] ?? null,
            externalOrderId: $payload['orderReference'] ?? null,
            amount: isset($payload['amount']) ? (float)$payload['amount'] : null,
            currency: $payload['currency'] ?? null,
            errorMessage: null,
            rawPayload: $payload,
        );
    }

    public function validateCallback(array $payload): bool
    {
        return true;
    }

    private function mapStatus(string $externalStatus): string
    {
        return match ($externalStatus) {
            'Approved' => PaymentModel::STATUS_PAID,
            'Pending', 'InProcessing', 'WaitingAuthComplete' => PaymentModel::STATUS_PENDING,
            'Declined', 'Expired', 'RefundInProcessing', 'Reverse', 'Voided' => PaymentModel::STATUS_FAILED,
            'Refunded' => PaymentModel::STATUS_REFUNDED,
            default => PaymentModel::STATUS_PENDING,
        };
    }
}