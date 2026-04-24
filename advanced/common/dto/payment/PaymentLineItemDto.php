<?php

namespace common\dto\payment;

final class PaymentLineItemDto
{
    public function __construct(
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $price,
        public readonly ?string $sku = null,
    ) {
    }
}