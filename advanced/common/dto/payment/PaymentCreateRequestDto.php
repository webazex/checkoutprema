<?php


namespace common\dto\payment;

final class PaymentCreateRequestDto
{
    public function __construct(
        public readonly int     $paymentId,
        public readonly int     $orderId,
        public readonly float   $amount,
        public readonly string  $currency,
        public readonly string  $description,
        public readonly string  $returnUrl,
        public readonly string  $callbackUrl,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
    )
    {
    }
}