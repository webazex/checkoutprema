<?php

declare(strict_types=1);

namespace common\enums\delivery;

enum DeliverySyncStatus: string
{
    case IDLE = 'idle';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}