<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliverySettlementReadDto
{
    public function __construct(
        public int     $id,
        public int     $providerId,
        public string  $providerCode,
        public int     $areaId,
        public string  $areaExternalRef,
        public string  $areaName,
        public string  $externalRef,
        public ?string $deliveryRef,
        public string  $name,
        public ?string $present = null,
        public ?string $settlementTypeCode = null,
        public ?string $districtName = null,
        public ?float  $latitude = null,
        public ?float  $longitude = null,
    )
    {
        DeliveryDtoAssertion::positiveInt($id, 'id');
        DeliveryDtoAssertion::positiveInt($providerId, 'providerId');
        DeliveryDtoAssertion::providerCode($providerCode);
        DeliveryDtoAssertion::positiveInt($areaId, 'areaId');
        DeliveryDtoAssertion::requiredString($areaExternalRef, 'areaExternalRef');
        DeliveryDtoAssertion::requiredString($areaName, 'areaName');
        DeliveryDtoAssertion::requiredString($externalRef, 'externalRef');
        DeliveryDtoAssertion::optionalString($deliveryRef, 'deliveryRef');
        DeliveryDtoAssertion::requiredString($name, 'name');
        DeliveryDtoAssertion::optionalString($present, 'present');
        DeliveryDtoAssertion::optionalString($settlementTypeCode, 'settlementTypeCode');
        DeliveryDtoAssertion::optionalString($districtName, 'districtName');
        DeliveryDtoAssertion::coordinates($latitude, $longitude);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null;
    }
}