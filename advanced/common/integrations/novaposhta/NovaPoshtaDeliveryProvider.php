<?php

declare(strict_types=1);

namespace common\integrations\novaposhta;

use common\contracts\delivery\DeliveryDirectorySourceInterface;
use common\contracts\delivery\DeliveryProviderInterface;
use common\enums\delivery\DeliveryProviderCapability;

final readonly class NovaPoshtaDeliveryProvider implements DeliveryProviderInterface
{
    public const CODE = 'nova_poshta';

    public function __construct(
        private NovaPoshtaDirectorySource $directorySource
    )
    {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'Нова пошта';
    }

    public function directorySource(): DeliveryDirectorySourceInterface
    {
        return $this->directorySource;
    }

    public function capabilities(): array
    {
        return [
            DeliveryProviderCapability::PICKUP_POINTS,
            DeliveryProviderCapability::POINT_SCHEDULES,
            DeliveryProviderCapability::POINT_COORDINATES,
        ];
    }
}