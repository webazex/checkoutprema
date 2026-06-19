<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

final readonly class NovaPoshtaSettlementDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $ref,
        public string $areaRef,
        public string $name,
        public ?string $areaName,
        public ?string $regionRef,
        public ?string $regionName,
        public ?string $settlementTypeRef,
        public ?string $settlementTypeName,
        public ?float $latitude,
        public ?float $longitude,
        public bool $hasWarehouse,
        public bool $addressDeliveryAllowed,
        public array $metadata = [],
    ) {
    }
}