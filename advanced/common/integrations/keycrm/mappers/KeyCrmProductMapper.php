<?php

declare(strict_types=1);

namespace common\integrations\keycrm\mappers;

use common\integrations\keycrm\dto\KeyCrmProductDto;
use InvalidArgumentException;

final class KeyCrmProductMapper
{
    public function mapOne(array $item): KeyCrmProductDto
    {
        $externalId = (int)($item['id'] ?? 0);
        $name = trim((string)($item['name'] ?? ''));
        $currencyCode = strtoupper(trim((string)($item['currency_code'] ?? 'UAH')));

        if ($externalId < 1) {
            throw new InvalidArgumentException('KeyCRM product id is required.');
        }

        if ($name === '') {
            throw new InvalidArgumentException(sprintf(
                'KeyCRM product #%d does not contain a valid name.',
                $externalId
            ));
        }

        $imageUrls = $this->extractImageUrls($item);
        $fallbackThumbnailUrl = $this->nullableString($item['thumbnail_url'] ?? null);

        if ($fallbackThumbnailUrl !== null && !in_array($fallbackThumbnailUrl, $imageUrls, true)) {
            array_unshift($imageUrls, $fallbackThumbnailUrl);
        }

        $thumbnailUrl = $imageUrls[0] ?? $fallbackThumbnailUrl;

        $stockQuantity = $this->extractStockQuantity($item);
        $reservedQuantity = $this->extractReservedQuantity($item);
        $availableQuantity = $this->extractAvailableQuantity(
            item: $item,
            stockQuantity: $stockQuantity,
            reservedQuantity: $reservedQuantity,
        );

        return new KeyCrmProductDto(
            externalId: $externalId,
            name: $name,
            description: $this->nullableString($item['description'] ?? null),
            thumbnailUrl: $thumbnailUrl,
            imageUrls: $imageUrls,

            // Raw/legacy value. Do not treat it as available-to-sell.
            quantity: (int)($item['quantity'] ?? 0),

            currencyCode: $currencyCode !== '' ? $currencyCode : 'UAH',
            price: (float)($item['price'] ?? 0),
            purchasedPrice: $this->nullableFloat($item['purchased_price'] ?? null),
            categoryId: $this->nullableInt($item['category_id'] ?? null),
            sku: $this->nullableString($item['sku'] ?? null),
            barcode: $this->nullableString($item['barcode'] ?? null),
            isArchived: (bool)($item['is_archived'] ?? false),
            weight: $this->nullableFloat($item['weight'] ?? null),
            length: $this->nullableFloat($item['length'] ?? null),
            width: $this->nullableFloat($item['width'] ?? null),
            height: $this->nullableFloat($item['height'] ?? null),
            customFields: is_array($item['custom_fields'] ?? null) ? $item['custom_fields'] : [],
            raw: $item,
            stockQuantity: $stockQuantity,
            reservedQuantity: $reservedQuantity,
            availableQuantity: $availableQuantity,
        );
    }

    /**
     * @return KeyCrmProductDto[]
     */
    public function mapMany(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $result[] = $this->mapOne($item);
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function extractImageUrls(array $item): array
    {
        $urls = [];

        if (array_key_exists('attachments_data', $item)) {
            $this->collectImageUrls($item['attachments_data'], $urls);
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function collectImageUrls(mixed $value, array &$urls): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            $url = trim($value);

            if ($url === '') {
                return;
            }

            if (preg_match('~^https?://~i', $url) || str_starts_with($url, '/')) {
                $urls[] = $url;
            }

            return;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return;
        }

        foreach (['url', 'src', 'thumbnail_url', 'image_url', 'original_url', 'full_url'] as $key) {
            if (!empty($value[$key]) && is_string($value[$key])) {
                $this->collectImageUrls($value[$key], $urls);
                return;
            }
        }

        foreach ($value as $item) {
            $this->collectImageUrls($item, $urls);
        }
    }

    private function extractStockQuantity(array $item): ?int
    {
        foreach ([
                     'quantity',
                     'stock_quantity',
                     'stockQuantity',
                     'in_stock',
                     'inStock',
                     'balance',
                 ] as $key) {
            if (array_key_exists($key, $item)) {
                return $this->nullableInt($item[$key]);
            }
        }

        return null;
    }

    private function extractReservedQuantity(array $item): ?int
    {
        foreach ([
                     'in_reserve',
                     'inReserve',
                     'reserved',
                     'reserve',
                     'reserved_quantity',
                     'reservedQuantity',
                     'quantity_reserved',
                     'quantityReserved',
                 ] as $key) {
            if (array_key_exists($key, $item)) {
                return $this->nullableInt($item[$key]);
            }
        }

        return null;
    }

    private function extractAvailableQuantity(
        array $item,
        ?int $stockQuantity,
        ?int $reservedQuantity,
    ): ?int {
        foreach ([
                     'available_quantity',
                     'availableQuantity',
                     'available',
                     'available_qty',
                     'availableQty',
                     'stock_available',
                     'stockAvailable',
                     'quantity_available',
                     'quantityAvailable',
                 ] as $key) {
            if (array_key_exists($key, $item)) {
                return max((int)$item[$key], 0);
            }
        }

        if ($stockQuantity !== null && $reservedQuantity !== null) {
            return max($stockQuantity - $reservedQuantity, 0);
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int)$value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float)$value;
    }
}