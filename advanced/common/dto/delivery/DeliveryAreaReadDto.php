<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliveryAreaReadDto
{
    public function __construct(
        public int    $id,
        public int    $providerId,
        public string $providerCode,
        public string $externalRef,
        public string $name,
    )
    {
        DeliveryDtoAssertion::positiveInt($id, 'id');
        DeliveryDtoAssertion::positiveInt($providerId, 'providerId');
        DeliveryDtoAssertion::providerCode($providerCode);
        DeliveryDtoAssertion::requiredString($externalRef, 'externalRef');
        DeliveryDtoAssertion::requiredString($name, 'name');
    }
}