<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

/**
 * @template T of object
 */
final readonly class DeliverySyncPageDto
{
    /**
     * @param list<T> $items
     */
    public function __construct(public array $items, public ?string $nextCursor = null, public ?int $totalCount = null)
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                'Delivery sync page items must be a list.'
            );
        }

        if ($nextCursor !== null && trim($nextCursor) === '') {
            throw new InvalidArgumentException(
                'Delivery sync page cursor must be null or a non-empty string.'
            );
        }

        if ($totalCount !== null && $totalCount < 0) {
            throw new InvalidArgumentException(
                'Delivery sync page total count must not be negative.'
            );
        }
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}