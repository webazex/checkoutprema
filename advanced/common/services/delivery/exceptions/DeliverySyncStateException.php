<?php

declare(strict_types=1);

namespace common\services\delivery\exceptions;

use RuntimeException;

final class DeliverySyncStateException extends RuntimeException
{
    public static function alreadyRunning(int $stateId): self
    {
        return new self(sprintf(
            'Delivery sync state #%d is already owned by an active worker.',
            $stateId
        ));
    }

    public static function ownershipLost(int $stateId): self
    {
        return new self(sprintf(
            'Delivery sync state #%d is no longer owned by this worker.',
            $stateId
        ));
    }
}