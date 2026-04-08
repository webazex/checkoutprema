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

        return new KeyCrmProductDto(
            externalId: $externalId,
            name: $name,
            description: $this->nullableString($item['description'] ?? null),
            thumbnailUrl: $this->nullableString($item['thumbnail_url'] ?? null),
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