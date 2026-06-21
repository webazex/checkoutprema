<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliveryProviderReadDto
{
    public function __construct(
        public int    $id,
        public string $code,
        public string $name,
        public bool   $isActive,
        public int    $sortOrder,
    )
    {
        DeliveryDtoAssertion::positiveInt($id, 'id');
        DeliveryDtoAssertion::providerCode($code);
        DeliveryDtoAssertion::requiredString($name, 'name');
    }
}