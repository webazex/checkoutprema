<?php


declare(strict_types=1);

namespace common\dto\cart;

final class CheckoutCartDto
{
    /**
     * @param CheckoutCartItemDto[] $items
     */
    public function __construct(
        public readonly int    $id,
        public readonly string $hash,
        public readonly string $sessionKey,
        public readonly string $status,
        public readonly string $sourceType,
        public readonly string $currency,
        public readonly int    $itemsCount,
        public readonly float  $subtotalAmount,
        public readonly float  $totalAmount,
        public readonly array  $items,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'hash' => $this->hash,
            'sessionKey' => $this->sessionKey,
            'status' => $this->status,
            'sourceType' => $this->sourceType,
            'currency' => $this->currency,
            'itemsCount' => $this->itemsCount,
            'subtotalAmount' => $this->subtotalAmount,
            'totalAmount' => $this->totalAmount,
            'items' => array_map(
                static fn(CheckoutCartItemDto $item) => $item->toArray(),
                $this->items
            ),
        ];
    }
}