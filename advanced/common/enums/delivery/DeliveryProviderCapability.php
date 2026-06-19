<?php

declare(strict_types=1);

namespace common\enums\delivery;

enum DeliveryProviderCapability: string
{
    case PICKUP_POINTS = 'pickup_points';
    case POINT_SCHEDULES = 'point_schedules';
    case POINT_COORDINATES = 'point_coordinates';
    case COURIER_DELIVERY = 'courier_delivery';
}