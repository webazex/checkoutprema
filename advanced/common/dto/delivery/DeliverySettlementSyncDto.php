<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliverySettlementSyncDto
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $providerCode,
        public string $areaExternalRef,
        public string $externalRef,
        public ?string $deliveryRef,
        public string $name,
        public ?string $present = null,
        public ?string $settlementTypeCode = null,
        public ?string $districtName = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public array $metadata = [],
    ) {
        DeliveryDtoAssertion::providerCode($providerCode);
        DeliveryDtoAssertion::requiredString($areaExternalRef, 'areaExternalRef');
        DeliveryDtoAssertion::requiredString($externalRef, 'externalRef');
        DeliveryDtoAssertion::optionalString($deliveryRef, 'deliveryRef');
        DeliveryDtoAssertion::requiredString($name, 'name');
        DeliveryDtoAssertion::optionalString($present, 'present');
        DeliveryDtoAssertion::optionalString($settlementTypeCode, 'settlementTypeCode');
        DeliveryDtoAssertion::optionalString($districtName, 'districtName');
        DeliveryDtoAssertion::coordinates($latitude, $longitude);
        DeliveryDtoAssertion::jsonEncodable($metadata, 'metadata');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null;
    }
}