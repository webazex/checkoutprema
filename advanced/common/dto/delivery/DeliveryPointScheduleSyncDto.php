<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

final readonly class DeliveryPointScheduleSyncDto
{
    public function __construct(
        public int     $weekday,
        public int     $intervalNo = 1,
        public ?string $opensAt = null,
        public ?string $closesAt = null,
        public bool    $isClosed = false,
        public ?string $validFrom = null,
        public ?string $validTo = null,
    )
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException(
                'Delivery schedule weekday must be between 1 and 7.'
            );
        }

        if ($intervalNo < 1) {
            throw new InvalidArgumentException(
                'Delivery schedule interval number must be greater than zero.'
            );
        }

        DeliveryDtoAssertion::time($opensAt, 'opensAt');
        DeliveryDtoAssertion::time($closesAt, 'closesAt');
        DeliveryDtoAssertion::date($validFrom, 'validFrom');
        DeliveryDtoAssertion::date($validTo, 'validTo');

        if ($isClosed && ($opensAt !== null || $closesAt !== null)) {
            throw new InvalidArgumentException(
                'Closed delivery schedule interval must not contain opening or closing time.'
            );
        }

        if (!$isClosed && ($opensAt === null || $closesAt === null)) {
            throw new InvalidArgumentException(
                'Open delivery schedule interval must contain both opening and closing time.'
            );
        }

        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            throw new InvalidArgumentException(
                'Delivery schedule validFrom must not be later than validTo.'
            );
        }
    }
}