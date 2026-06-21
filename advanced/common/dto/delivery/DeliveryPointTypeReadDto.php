<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliveryPointTypeReadDto
{
    public function __construct(
        public int    $id,
        public string $code,
        public string $name,
        public int    $sortOrder,
    )
    {
        DeliveryDtoAssertion::positiveInt($id, 'id');
        DeliveryDtoAssertion::normalizedCode($code, 'code');
        DeliveryDtoAssertion::requiredString($name, 'name');
    }
}