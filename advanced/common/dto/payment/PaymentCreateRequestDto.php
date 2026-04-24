<?php

namespace common\dto\payment;

final class PaymentCreateRequestDto
{
    /**
     * @param PaymentLineItemDto[] $items
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly string $provider,
        public readonly string $orderReference,
        public readonly int $orderDate,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly array $items,
        public readonly string $returnUrl,
        public readonly string $callbackUrl,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerFirstName = null,
        public readonly ?string $customerLastName = null,
        public readonly array $metadata = [],
    ) {
    }
}