<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

final readonly class NovaPoshtaDimensionsDto
{
    public function __construct(public int $width, public int $height, public int $length)
    {
    }
}