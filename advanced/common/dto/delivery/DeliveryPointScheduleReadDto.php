<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

final readonly class DeliveryPointScheduleReadDto
{
    public function __construct(
        public int $weekday,
        public int $intervalNo,
        public ?string $opensAt,
        public ?string $closesAt,
        public bool $isClosed,
        public ?string $validFrom = null,
        public ?string $validTo = null,
    ) {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException(
                'Delivery schedule weekday must be between 1 and 7.'
            );
        }

        DeliveryDtoAssertion::positiveInt($intervalNo, 'intervalNo');
        DeliveryDtoAssertion::time($opensAt, 'opensAt');
        DeliveryDtoAssertion::time($closesAt, 'closesAt');
        DeliveryDtoAssertion::date($validFrom, 'validFrom');
        DeliveryDtoAssertion::date($validTo, 'validTo');

        if ($isClosed && ($opensAt !== null || $closesAt !== null)) {
            throw new InvalidArgumentException(
                'Closed schedule interval must not contain working hours.'
            );
        }

        if (!$isClosed && ($opensAt === null || $closesAt === null)) {
            throw new InvalidArgumentException(
                'Open schedule interval must contain both working hours.'
            );
        }

        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            throw new InvalidArgumentException(
                'Schedule validFrom must not be later than validTo.'
            );
        }
    }
}