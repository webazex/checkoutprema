<?php

declare(strict_types=1);

namespace common\integrations\keycrm\dto;

final class KeyCrmProductDto
{
    public function __construct(
        public readonly int $externalId,
        public readonly string $name,
        public readonly ?string $description,

        /**
         * Main product image URL.
         * Usually the first URL from KeyCRM attachments_data.
         */
        public readonly ?string $thumbnailUrl,

        /**
         * Ordered product image URLs from KeyCRM.
         * The first item is considered the main image.
         *
         * @var string[]
         */
        public readonly array $imageUrls,

        /**
         * Legacy/raw KeyCRM quantity value.
         * Do not write this directly to product.quantity unless it is proven
         * to be available-to-sell quantity.
         */
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

        /**
         * Total stock quantity from KeyCRM, if available.
         */
        public readonly ?int $stockQuantity = null,

        /**
         * Reserved quantity from KeyCRM, if available.
         */
        public readonly ?int $reservedQuantity = null,

        /**
         * Available-to-sell quantity.
         * This is the only value that may be written to product.quantity.
         */
        public readonly ?int $availableQuantity = null,
    ) {
    }
}