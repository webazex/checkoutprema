<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

use InvalidArgumentException;

final readonly class NovaPoshtaSettlementPageDto
{
    /**
     * @param list<NovaPoshtaSettlementDto> $items
     */
    public function __construct(
        public array $items,
        public int $apiTotalCount,
        public int $page,
        public int $limit,
    ) {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                'Nova Poshta settlements must be provided as a list.'
            );
        }

        if ($apiTotalCount < 0) {
            throw new InvalidArgumentException(
                'Nova Poshta settlement total count must not be negative.'
            );
        }

        if ($page < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta settlement page must be greater than zero.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta settlement limit must be greater than zero.'
            );
        }
    }

    public function hasMore(): bool
    {
        return $this->page * $this->limit < $this->apiTotalCount;
    }
}