<?php

declare(strict_types=1);

namespace common\integrations\keycrm\mappers;

use common\integrations\keycrm\dto\KeyCrmCategoryDto;
use DomainException;

final class KeyCrmCategoryMapper
{
    public function mapOne(array $item): KeyCrmCategoryDto
    {
        $externalId = $this->nullableInt($item['id'] ?? null);

        if ($externalId === null) {
            throw new DomainException('KeyCRM category item has no id.');
        }

        $name = $this->nullableString($item['name'] ?? $item['title'] ?? null);

        if ($name === null) {
            $name = 'Category ' . $externalId;
        }

        return new KeyCrmCategoryDto(
            externalId: $externalId,
            parentExternalId: $this->nullableInt($item['parent_id'] ?? $item['parentId'] ?? null),
            name: $name,
            description: $this->nullableString($item['description'] ?? null),
            thumbnailUrl: $this->nullableString($item['thumbnail_url'] ?? $item['image'] ?? null),
            sortOrder: (int)($item['sort_order'] ?? $item['position'] ?? $item['sort'] ?? 0),
            isArchived: (bool)($item['is_archived'] ?? $item['archived'] ?? false),
            raw: $item,
        );
    }

    /**
     * @return KeyCrmCategoryDto[]
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
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}