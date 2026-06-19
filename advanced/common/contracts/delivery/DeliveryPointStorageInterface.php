<?php

declare(strict_types=1);

namespace common\contracts\delivery;

use common\dto\delivery\DeliveryPointReadDto;

interface DeliveryPointStorageInterface
{
    public function findById(int $id): ?DeliveryPointReadDto;

    public function findSelectableById(int $id): ?DeliveryPointReadDto;

    public function findSelectableByProviderAndExternalRef(
        string $providerCode,
        string $externalRef
    ): ?DeliveryPointReadDto;
}