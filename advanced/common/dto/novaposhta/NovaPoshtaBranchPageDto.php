<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

final readonly class NovaPoshtaBranchPageDto
{
    /**
     * @param list<NovaPoshtaBranchDto> $items
     */
    public function __construct(public array $items, public int $totalCount, public int $page, public int $limit)
    {
    }

    public function hasMore(): bool
    {
        return $this->page * $this->limit < $this->totalCount;
    }
}