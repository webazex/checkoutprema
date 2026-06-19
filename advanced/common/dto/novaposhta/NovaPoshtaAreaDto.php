<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

final readonly class NovaPoshtaAreaDto
{
    public function __construct(
        public string $ref,
        public string $name,
        public ?string $centerRef = null,
        public ?string $nameRu = null,
    ) {
    }
}