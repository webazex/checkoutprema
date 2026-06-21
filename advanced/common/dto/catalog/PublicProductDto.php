<?php

declare(strict_types=1);

namespace common\dto\catalog;

final class PublicProductDto
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly string  $slug,
        public readonly ?string $sku,
        public readonly ?string $description,
        public readonly float   $price,
        public readonly string  $currency,
        public readonly int     $quantity,
        public readonly bool    $isAvailable,
        public readonly ?string $thumbnailUrl,
        public readonly ?string $categoryExternalId,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'isAvailable' => $this->isAvailable,
            'thumbnailUrl' => $this->thumbnailUrl,
            'categoryExternalId' => $this->categoryExternalId,
        ];
    }
}