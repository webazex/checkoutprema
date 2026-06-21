<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

use common\enums\novaposhta\NovaPoshtaWarehouseType;

final readonly class NovaPoshtaDeliveryPointDto
{
    public function __construct(
        public string                   $ref,
        public string                   $number,
        public NovaPoshtaWarehouseType  $type,
        public string                   $description,
        public string                   $shortAddress,
        public string                   $cityRef,
        public string                   $settlementRef,
        public string                   $settlementName,
        public string                   $areaName,
        public ?string                  $regionName,
        public ?float                   $latitude,
        public ?float                   $longitude,
        public ?float                   $totalMaxWeightAllowed,
        public ?float                   $placeMaxWeightAllowed,
        public ?NovaPoshtaDimensionsDto $sendingDimensions,
        public ?NovaPoshtaDimensionsDto $receivingDimensions
    )
    {
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}