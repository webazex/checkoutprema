<?php

declare(strict_types=1);

namespace common\dto\delivery;

final readonly class DeliveryPointSearchResultDto
{
    public function __construct(
        public DeliveryPointReadDto $point,
        public string               $label,
        public ?int                 $distanceMeters = null,
    )
    {
        DeliveryDtoAssertion::requiredString($label, 'label');

        if ($distanceMeters !== null) {
            DeliveryDtoAssertion::nonNegativeInt(
                $distanceMeters,
                'distanceMeters'
            );
        }
    }

    public function hasDistance(): bool
    {
        return $this->distanceMeters !== null;
    }
}