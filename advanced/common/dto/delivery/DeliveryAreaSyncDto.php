<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliveryAreaSyncDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(public string $providerCode, public string $externalRef, public string $name, public array $metadata = [])
    {
        DeliveryDtoAssertion::providerCode($providerCode);
        DeliveryDtoAssertion::requiredString($externalRef, 'externalRef');
        DeliveryDtoAssertion::requiredString($name, 'name');
        DeliveryDtoAssertion::jsonEncodable($metadata, 'metadata');
    }
}