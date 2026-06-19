<?php

declare(strict_types=1);

namespace common\contracts\delivery;

use common\enums\delivery\DeliveryProviderCapability;

interface DeliveryProviderInterface
{
    public function code(): string;

    public function name(): string;

    public function directorySource(): DeliveryDirectorySourceInterface;

    /**
     * @return list<DeliveryProviderCapability>
     */
    public function capabilities(): array;
}