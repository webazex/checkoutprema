<?php

declare(strict_types=1);

namespace common\integrations\keycrm\dto;

final class KeyCrmCategoryDto
{
    public function __construct(
        public readonly int     $externalId,
        public readonly ?int    $parentExternalId,
        public readonly string  $name,
        public readonly ?string $description,
        public readonly ?string $thumbnailUrl,
        public readonly int     $sortOrder,
        public readonly bool    $isArchived,
        public readonly array   $raw,
    )
    {
    }
}