<?php

declare(strict_types=1);

namespace common\enums\delivery;

enum DeliverySyncScope: string
{
    case AREAS = 'areas';
    case SETTLEMENTS = 'settlements';
    case POINTS = 'points';
    case SCHEDULES = 'schedules';
}