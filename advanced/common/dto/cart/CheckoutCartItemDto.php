<?php


declare(strict_types=1);

namespace common\dto\cart;

final class CheckoutCartItemDto
{
    public function __construct(
        public readonly int     $id,
        public readonly ?int    $productId,
        public readonly string  $title,
        public readonly ?string $sku,
        public readonly float   $price,
        public readonly int     $quantity,
        public readonly float   $subtotal,
        public readonly string  $currency,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'title' => $this->title,
            'sku' => $this->sku,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal,
            'currency' => $this->currency,
        ];
    }
}