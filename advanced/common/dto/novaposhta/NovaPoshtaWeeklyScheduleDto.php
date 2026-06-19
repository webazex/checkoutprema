<?php

declare(strict_types=1);

namespace common\dto\novaposhta;

use InvalidArgumentException;

final readonly class NovaPoshtaWeeklyScheduleDto
{
    public function __construct(
        public ?string $monday = null,
        public ?string $tuesday = null,
        public ?string $wednesday = null,
        public ?string $thursday = null,
        public ?string $friday = null,
        public ?string $saturday = null,
        public ?string $sunday = null,
    ) {
        foreach ($this->intervalsByWeekday() as $weekday => $interval) {
            if (trim($interval) === '') {
                throw new InvalidArgumentException(sprintf(
                    'Nova Poshta schedule interval for weekday %d must not be empty.',
                    $weekday
                ));
            }
        }
    }

    /**
     * ISO-8601 weekday:
     * 1 — Monday
     * 7 — Sunday
     *
     * @return array<int, string>
     */
    public function intervalsByWeekday(): array
    {
        return array_filter(
            [
                1 => $this->monday,
                2 => $this->tuesday,
                3 => $this->wednesday,
                4 => $this->thursday,
                5 => $this->friday,
                6 => $this->saturday,
                7 => $this->sunday,
            ],
            static fn (?string $interval): bool => $interval !== null
        );
    }

    public function isEmpty(): bool
    {
        return $this->intervalsByWeekday() === [];
    }
}