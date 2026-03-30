<?php

namespace common\services\payment\gateways;

use common\contracts\payment\PaymentGatewayInterface;
use common\dto\payment\PaymentCallbackResponseDto;
use common\dto\payment\PaymentCallbackResultDto;
use common\dto\payment\PaymentCreateRequestDto;
use common\dto\payment\PaymentCreateResultDto;
use common\dto\payment\PaymentLineItemDto;
use common\dto\payment\PaymentNextActionDto;
use common\models\payment\PaymentModel;

final class WayForPayGateway implements PaymentGatewayInterface
{
    private const PAY_URL = 'https://secure.wayforpay.com/pay';

    public function __construct(
        private readonly string $merchantAccount,
        private readonly string $merchantSecretKey,
        private readonly string $merchantDomainName,
    ) {
    }

    public function getCode(): string
    {
        return PaymentModel::PROVIDER_WAYFORPAY;
    }

    public function createPayment(PaymentCreateRequestDto $request): PaymentCreateResultDto
    {
        $productNames = [];
        $productPrices = [];
        $productCounts = [];

        foreach ($request->items as $item) {
            if (!$item instanceof PaymentLineItemDto) {
                continue;
            }

            $productNames[] = $item->name;
            $productPrices[] = $this->stringifyAmount($item->price);
            $productCounts[] = (string)$item->quantity;
        }

        $payload = [
            'merchantAccount' => $this->merchantAccount,
            'merchantAuthType' => 'SimpleSignature',
            'merchantDomainName' => $this->merchantDomainName,
            'merchantTransactionType' => 'AUTO',
            'merchantTransactionSecureType' => 'AUTO',
            'apiVersion' => 2,
            'returnUrl' => $request->returnUrl,
            'serviceUrl' => $request->callbackUrl,
            'orderReference' => $request->orderReference,
            'orderDate' => $request->orderDate,
            'amount' => $this->stringifyAmount($request->amount),
            'currency' => $request->currency,
            'productName' => $productNames,
            'productPrice' => $productPrices,
            'productCount' => $productCounts,
        ];

        if (!empty($request->customerEmail)) {
            $payload['clientEmail'] = $request->customerEmail;
        }

        if (!empty($request->customerPhone)) {
            $payload['clientPhone'] = $request->customerPhone;
        }

        if (!empty($request->customerFirstName)) {
            $payload['clientFirstName'] = $request->customerFirstName;
        }

        if (!empty($request->customerLastName)) {
            $payload['clientLastName'] = $request->customerLastName;
        }

        $signatureBase = $this->buildPurchaseSignatureString(
            orderReference: $request->orderReference,
            orderDate: $request->orderDate,
            amount: $request->amount,
            currency: $request->currency,
            items: $request->items,
        );

        $payload['merchantSignature'] = hash_hmac('md5', $signatureBase, $this->merchantSecretKey);

        $nextAction = new PaymentNextActionDto(
            type: PaymentNextActionDto::TYPE_REDIRECT_POST,
            url: self::PAY_URL,
            method: 'POST',
            payload: $payload,
        );

        return new PaymentCreateResultDto(
            provider: $this->getCode(),
            status: PaymentModel::STATUS_PENDING,
            externalId: null,
            externalOrderId: $request->orderReference,
            redirectUrl: self::PAY_URL,
            nextAction: $nextAction,
            errorMessage: null,
            rawResponse: [
                'formAction' => self::PAY_URL,
                'formFields' => $payload,
                'signatureBase' => $signatureBase,
            ],
        );
    }

    public function validateCallback(array $payload): bool
    {
        $required = [
            'merchantAccount',
            'orderReference',
            'merchantSignature',
            'amount',
            'currency',
            'authCode',
            'cardPan',
            'transactionStatus',
            'reasonCode',
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return false;
            }
        }

        if ((string)$payload['merchantAccount'] !== $this->merchantAccount) {
            return false;
        }

        $expectedSignature = hash_hmac(
            'md5',
            $this->buildCallbackSignatureString($payload),
            $this->merchantSecretKey
        );

        return hash_equals($expectedSignature, (string)$payload['merchantSignature']);
    }

    public function parseCallback(array $payload): PaymentCallbackResultDto
    {
        $isValid = $this->validateCallback($payload);
        $providerStatus = isset($payload['transactionStatus']) ? (string)$payload['transactionStatus'] : null;

        return new PaymentCallbackResultDto(
            provider: $this->getCode(),
            isValid: $isValid,
            status: $this->mapStatus($providerStatus),
            externalId: isset($payload['transactionId']) ? (string)$payload['transactionId'] : null,
            externalOrderId: isset($payload['orderReference']) ? (string)$payload['orderReference'] : null,
            amount: isset($payload['amount']) ? (float)$payload['amount'] : null,
            currency: isset($payload['currency']) ? (string)$payload['currency'] : null,
            paymentMethod: isset($payload['paymentSystem']) ? (string)$payload['paymentSystem'] : null,
            providerStatus: $providerStatus,
            errorMessage: $isValid ? $this->buildProviderErrorMessage($payload) : 'Invalid callback signature.',
            rawPayload: $payload,
        );
    }

    public function buildCallbackResponse(PaymentCallbackResultDto $result): PaymentCallbackResponseDto
    {
        $status = $result->isValid ? 'accept' : 'decline';
        $time = time();
        $orderReference = (string)($result->externalOrderId ?? '');

        $signatureString = implode(';', [
            $orderReference,
            $status,
            $time,
        ]);

        $signature = hash_hmac('md5', $signatureString, $this->merchantSecretKey);

        $body = json_encode([
            'orderReference' => $orderReference,
            'status' => $status,
            'time' => $time,
            'signature' => $signature,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new PaymentCallbackResponseDto(
            statusCode: 200,
            body: $body === false ? '{}' : $body,
        );
    }

    /**
     * @param PaymentLineItemDto[] $items
     */
    private function buildPurchaseSignatureString(
        string $orderReference,
        int $orderDate,
        float $amount,
        string $currency,
        array $items
    ): string {
        $parts = [
            $this->merchantAccount,
            $this->merchantDomainName,
            $orderReference,
            (string)$orderDate,
            $this->stringifyAmount($amount),
            $currency,
        ];

        foreach ($items as $item) {
            $parts[] = $item->name;
        }

        foreach ($items as $item) {
            $parts[] = (string)$item->quantity;
        }

        foreach ($items as $item) {
            $parts[] = $this->stringifyAmount($item->price);
        }

        return implode(';', $parts);
    }

    private function buildCallbackSignatureString(array $payload): string
    {
        return implode(';', [
            (string)$payload['merchantAccount'],
            (string)$payload['orderReference'],
            $this->stringifyAmount($payload['amount']),
            (string)$payload['currency'],
            (string)$payload['authCode'],
            (string)$payload['cardPan'],
            (string)$payload['transactionStatus'],
            (string)$payload['reasonCode'],
        ]);
    }

    private function mapStatus(?string $externalStatus): string
    {
        return match ($externalStatus) {
            'Approved' => PaymentModel::STATUS_PAID,
            'Authorized', 'WaitingAuthComplete' => PaymentModel::STATUS_AUTHORIZED,
            'Pending', 'InProcessing', 'CreatedAwaiting3DS', 'RefundInProcessing' => PaymentModel::STATUS_PENDING,
            'Refunded' => PaymentModel::STATUS_REFUNDED,
            'Voided', 'Reversed' => PaymentModel::STATUS_CANCELLED,
            'Declined', 'Expired', 'Failed' => PaymentModel::STATUS_FAILED,
            default => PaymentModel::STATUS_PENDING,
        };
    }

    private function buildProviderErrorMessage(array $payload): ?string
    {
        $reason = trim((string)($payload['reason'] ?? ''));
        $reasonCode = isset($payload['reasonCode']) ? (string)$payload['reasonCode'] : '';

        if ($reason === '' && $reasonCode === '') {
            return null;
        }

        if ($reason !== '' && $reasonCode !== '' && $reasonCode !== '1100') {
            return "{$reason} (#{$reasonCode})";
        }

        if ($reason !== '' && $reasonCode === '') {
            return $reason;
        }

        if ($reason === '' && $reasonCode !== '' && $reasonCode !== '1100') {
            return "Provider reason code #{$reasonCode}";
        }

        return null;
    }

    private function stringifyAmount(int|float|string $amount): string
    {
        if (is_string($amount)) {
            return trim($amount);
        }

        $formatted = number_format((float)$amount, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}