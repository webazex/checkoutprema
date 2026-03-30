<?php

namespace common\dto\payment;

final class PaymentCallbackResultDto
{
    public function __construct(
        public readonly string  $provider,
        public readonly bool    $isValid,
        public readonly string  $status,
        public readonly ?string $externalId = null,
        public readonly ?string $externalOrderId = null,
        public readonly ?float  $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $errorMessage = null,
        public readonly array   $rawPayload = [],
    )
    {
    }
}