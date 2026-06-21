<?php

namespace common\dto\payment;

final class PaymentCreateResultDto
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly string                $provider,
        public readonly string                $status,
        public readonly ?string               $externalId = null,
        public readonly ?string               $externalOrderId = null,
        public readonly ?string               $redirectUrl = null,
        public readonly ?PaymentNextActionDto $nextAction = null,
        public readonly ?string               $errorMessage = null,
        public readonly array                 $rawResponse = [],
    )
    {
    }

    public function isSuccessful(): bool
    {
        return $this->errorMessage === null;
    }
}