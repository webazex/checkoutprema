<?php

namespace common\services\payment;

use common\contracts\payment\PaymentGatewayInterface;
use RuntimeException;

final class PaymentGatewayRegistry
{
    /**
     * @param PaymentGatewayInterface[] $gateways
     */
    public function __construct(
        private readonly array $gateways
    )
    {
    }

    public function get(string $provider): PaymentGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->getCode() === $provider) {
                return $gateway;
            }
        }

        throw new RuntimeException("Payment gateway not found for provider: {$provider}");
    }
}