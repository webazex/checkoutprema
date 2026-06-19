<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\contracts\delivery\DeliveryProviderInterface;
use common\enums\delivery\DeliveryProviderCapability;
use InvalidArgumentException;
use OutOfBoundsException;
use UnexpectedValueException;

final class DeliveryProviderRegistry
{
    /**
     * @var array<string, DeliveryProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<DeliveryProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    public function get(string $code): DeliveryProviderInterface
    {
        $code = $this->normalizeCode($code);

        if (!isset($this->providers[$code])) {
            throw new OutOfBoundsException(sprintf(
                'Delivery provider "%s" is not registered.',
                $code
            ));
        }

        return $this->providers[$code];
    }

    public function has(string $code): bool
    {
        try {
            $code = $this->normalizeCode($code);
        } catch (InvalidArgumentException) {
            return false;
        }

        return isset($this->providers[$code]);
    }

    public function supports(string $code, DeliveryProviderCapability $capability): bool
    {
        return in_array(
            $capability,
            $this->get($code)->capabilities(),
            true
        );
    }

    /**
     * @return array<string, DeliveryProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->providers);
    }

    private function add(DeliveryProviderInterface $provider): void
    {
        $rawCode = $provider->code();
        $code = $this->normalizeCode($rawCode);

        if ($rawCode !== $code) {
            throw new InvalidArgumentException(sprintf(
                'Delivery provider code "%s" must already be normalized as "%s".',
                $rawCode,
                $code
            ));
        }

        if (trim($provider->name()) === '') {
            throw new InvalidArgumentException(sprintf(
                'Delivery provider "%s" must have a non-empty name.',
                $code
            ));
        }

        if (isset($this->providers[$code])) {
            throw new InvalidArgumentException(sprintf(
                'Delivery provider "%s" is already registered.',
                $code
            ));
        }

        $this->validateCapabilities($provider);

        $this->providers[$code] = $provider;
    }

    private function validateCapabilities(DeliveryProviderInterface $provider): void
    {
        $seen = [];

        foreach ($provider->capabilities() as $capability) {
            if (!$capability instanceof DeliveryProviderCapability) {
                throw new UnexpectedValueException(sprintf(
                    'Delivery provider "%s" returned an invalid capability.',
                    $provider->code()
                ));
            }

            if (isset($seen[$capability->value])) {
                throw new UnexpectedValueException(sprintf(
                    'Delivery provider "%s" returned duplicate capability "%s".',
                    $provider->code(),
                    $capability->value
                ));
            }

            $seen[$capability->value] = true;
        }
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));

        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid delivery provider code "%s".',
                $code
            ));
        }

        return $code;
    }
}