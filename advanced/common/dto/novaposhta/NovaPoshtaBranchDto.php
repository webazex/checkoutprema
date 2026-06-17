<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

use common\enums\novaposhta\NovaPoshtaBranchType;

final readonly class NovaPoshtaBranchDto
{
    public function __construct(
        public string $ref,
        public string $number,
        public NovaPoshtaBranchType $type,
        public string $description,
        public string $shortAddress,
        public string $cityRef,
        public string $settlementRef,
        public string $settlementName,
        public string $areaName,
        public ?string $regionName,
        public ?float $latitude,
        public ?float $longitude,
        public string $status,
        public bool $denyToSelect,
        public ?float $placeMaxWeightAllowed,
        public ?NovaPoshtaDimensionsDto $sendingDimensions,
        public ?NovaPoshtaDimensionsDto $receivingDimensions,
    ) {
    }
}