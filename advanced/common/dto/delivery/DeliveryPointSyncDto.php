<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

final readonly class DeliveryPointSyncDto
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<DeliveryPointScheduleSyncDto> $schedules
     */
    public function __construct(
        public string $providerCode,
        public string $settlementExternalRef,
        public ?string $settlementDeliveryRef,
        public string $externalRef,
        public string $typeCode,
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
        DeliveryDtoAssertion::providerCode($providerCode);
        DeliveryDtoAssertion::requiredString($settlementExternalRef, 'settlementExternalRef');
        DeliveryDtoAssertion::optionalString($settlementDeliveryRef, 'settlementDeliveryRef');
        DeliveryDtoAssertion::requiredString($externalRef, 'externalRef');
        DeliveryDtoAssertion::normalizedCode($typeCode, 'typeCode');
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

        $scheduleKeys = [];

        foreach ($schedules as $schedule) {
            if (!$schedule instanceof DeliveryPointScheduleSyncDto) {
                throw new InvalidArgumentException(
                    'Delivery point schedules must contain only DeliveryPointScheduleSyncDto objects.'
                );
            }

            $key = implode(':', [
                $schedule->weekday,
                $schedule->intervalNo,
                $schedule->validFrom ?? '',
            ]);

            if (isset($scheduleKeys[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate delivery schedule interval "%s".',
                    $key
                ));
            }

            $scheduleKeys[$key] = true;
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