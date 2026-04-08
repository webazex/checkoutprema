<?php

declare(strict_types=1);

namespace common\integrations\keycrm\dto;

final class KeyCrmProductDto
{
    public function __construct(
        public readonly int $externalId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $thumbnailUrl,
        public readonly int $quantity,
        public readonly string $currencyCode,
        public readonly float $price,
        public readonly ?float $purchasedPrice,
        public readonly ?int $categoryId,
        public readonly ?string $sku,
        public readonly ?string $barcode,
        public readonly bool $isArchived,
        public readonly ?float $weight,
        public readonly ?float $length,
        public readonly ?float $width,
        public readonly ?float $height,
        public readonly array $customFields,
        public readonly array $raw,
    ) {
    }
}