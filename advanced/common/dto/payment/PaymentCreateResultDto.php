<?php

namespace common\dto\payment;

final class PaymentCreateResultDto
{
    public function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly ?string $externalId = null,
        public readonly ?string $externalOrderId = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->errorMessage === null;
    }
}