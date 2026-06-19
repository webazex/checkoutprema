<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

final readonly class DeliveryWriteBatchResultDto
{
    public function __construct(
        public int $processedCount,
        public int $createdCount,
        public int $updatedCount,
        public int $archivedCount = 0,
    ) {
        DeliveryDtoAssertion::nonNegativeInt(
            $processedCount,
            'processedCount'
        );

        DeliveryDtoAssertion::nonNegativeInt(
            $createdCount,
            'createdCount'
        );

        DeliveryDtoAssertion::nonNegativeInt(
            $updatedCount,
            'updatedCount'
        );

        DeliveryDtoAssertion::nonNegativeInt(
            $archivedCount,
            'archivedCount'
        );

        if ($createdCount + $updatedCount > $processedCount) {
            throw new InvalidArgumentException(
                'Created and updated counters must not exceed processedCount.'
            );
        }
    }

    public static function empty(): self
    {
        return new self(
            processedCount: 0,
            createdCount: 0,
            updatedCount: 0,
        );
    }

    public function merge(self $other): self
    {
        return new self(
            processedCount: $this->processedCount + $other->processedCount,
            createdCount: $this->createdCount + $other->createdCount,
            updatedCount: $this->updatedCount + $other->updatedCount,
            archivedCount: $this->archivedCount + $other->archivedCount,
        );
    }
}