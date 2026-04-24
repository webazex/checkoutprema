<?php

namespace common\services\payment;

use common\contracts\payment\PaymentGatewayInterface;
use RuntimeException;

final class PaymentGatewayRegistry
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    private array $gatewaysByCode = [];

    /**
     * @param PaymentGatewayInterface[] $gateways
     */
    public function __construct(array $gateways)
    {
        foreach ($gateways as $gateway) {
            $code = $gateway->getCode();

            if (isset($this->gatewaysByCode[$code])) {
                throw new RuntimeException("Duplicate payment gateway registered for provider: {$code}");
            }

            $this->gatewaysByCode[$code] = $gateway;
        }
    }

    public function has(string $provider): bool
    {
        return isset($this->gatewaysByCode[$provider]);
    }

    public function get(string $provider): PaymentGatewayInterface
    {
        if (!$this->has($provider)) {
            throw new RuntimeException("Payment gateway not found for provider: {$provider}");
        }

        return $this->gatewaysByCode[$provider];
    }

    /**
     * @return PaymentGatewayInterface[]
     */
    public function all(): array
    {
        return array_values($this->gatewaysByCode);
    }
}