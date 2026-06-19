<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

final readonly class DeliveryPointReadDto
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<DeliveryPointScheduleReadDto> $schedules
     */
    public function __construct(
        public int $id,
        public int $providerId,
        public string $providerCode,
        public string $providerName,
        public int $areaId,
        public string $areaExternalRef,
        public string $areaName,
        public int $settlementId,
        public string $settlementExternalRef,
        public ?string $settlementDeliveryRef,
        public string $settlementName,
        public int $typeId,
        public string $typeCode,
        public string $typeName,
        public string $externalRef,
        public ?string $externalTypeRef,
        public ?string $number,
        public ?string $name,
        public ?string $description,
        public string $address,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $sourceCategory,
        public ?string $sourceStatus,
        public bool $isActive,
        public bool $isSelectable,
        public array $metadata = [],
        public array $schedules = [],
    ) {
        DeliveryDtoAssertion::positiveInt($id, 'id');
        DeliveryDtoAssertion::positiveInt($providerId, 'providerId');
        DeliveryDtoAssertion::providerCode($providerCode);
        DeliveryDtoAssertion::requiredString($providerName, 'providerName');

        DeliveryDtoAssertion::positiveInt($areaId, 'areaId');
        DeliveryDtoAssertion::requiredString($areaExternalRef, 'areaExternalRef');
        DeliveryDtoAssertion::requiredString($areaName, 'areaName');

        DeliveryDtoAssertion::positiveInt($settlementId, 'settlementId');
        DeliveryDtoAssertion::requiredString(
            $settlementExternalRef,
            'settlementExternalRef'
        );
        DeliveryDtoAssertion::optionalString(
            $settlementDeliveryRef,
            'settlementDeliveryRef'
        );
        DeliveryDtoAssertion::requiredString($settlementName, 'settlementName');

        DeliveryDtoAssertion::positiveInt($typeId, 'typeId');
        DeliveryDtoAssertion::normalizedCode($typeCode, 'typeCode');
        DeliveryDtoAssertion::requiredString($typeName, 'typeName');

        DeliveryDtoAssertion::requiredString($externalRef, 'externalRef');
        DeliveryDtoAssertion::optionalString($externalTypeRef, 'externalTypeRef');
        DeliveryDtoAssertion::optionalString($number, 'number');
        DeliveryDtoAssertion::optionalString($name, 'name');
        DeliveryDtoAssertion::optionalString($description, 'description');
        DeliveryDtoAssertion::requiredString($address, 'address');
        DeliveryDtoAssertion::coordinates($latitude, $longitude);
        DeliveryDtoAssertion::optionalString($sourceCategory, 'sourceCategory');
        DeliveryDtoAssertion::optionalString($sourceStatus, 'sourceStatus');
        DeliveryDtoAssertion::jsonEncodable($metadata, 'metadata');

        if ($isSelectable && !$isActive) {
            throw new InvalidArgumentException(
                'Selectable delivery point must also be active.'
            );
        }

        if (!array_is_list($schedules)) {
            throw new InvalidArgumentException(
                'Delivery point schedules must be a list.'
            );
        }

        foreach ($schedules as $schedule) {
            if (!$schedule instanceof DeliveryPointScheduleReadDto) {
                throw new InvalidArgumentException(
                    'Delivery point schedules contain an invalid object.'
                );
            }
        }
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null;
    }

    public function hasSchedule(): bool
    {
        return $this->schedules !== [];
    }
}