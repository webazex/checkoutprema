<?php

declare(strict_types=1);

namespace common\mappers\novaposhta;

use common\dto\delivery\DeliveryPointScheduleSyncDto;
use common\dto\delivery\DeliveryPointSyncDto;
use common\dto\novaposhta\NovaPoshtaDimensionsDto;
use common\dto\novaposhta\NovaPoshtaWarehouseDto;
use common\dto\novaposhta\NovaPoshtaWeeklyScheduleDto;
use common\enums\novaposhta\NovaPoshtaWarehouseType;
use InvalidArgumentException;
use UnexpectedValueException;

final class NovaPoshtaDirectoryMapper
{
    public const PROVIDER_CODE = 'nova_poshta';

    private const POINT_TYPE_POST_OFFICE = 'post_office';
    private const POINT_TYPE_CARGO_BRANCH = 'cargo_branch';

    private const SELECTABLE_CATEGORY = 'Branch';
    private const ACTIVE_STATUS = 'Working';

    private const CLOSED_MARKERS = [
        '-',
        'closed',
        'day off',
        'вихідний',
        'выходной',
        'не працює',
        'не работает',
    ];

    /**
     * Преобразует provider-specific DTO отделения
     * в общий DTO синхронизации delivery directory.
     */
    public function mapPoint(
        NovaPoshtaWarehouseDto $warehouse
    ): DeliveryPointSyncDto {
        $isActive = $warehouse->status === self::ACTIVE_STATUS;

        $isSelectable = $isActive
            && $warehouse->category === self::SELECTABLE_CATEGORY
            && !$warehouse->denyToSelect;

        return new DeliveryPointSyncDto(
            providerCode: self::PROVIDER_CODE,
            settlementExternalRef: $warehouse->settlementRef,
            settlementDeliveryRef: $warehouse->cityRef,
            externalRef: $warehouse->ref,
            typeCode: $this->mapPointTypeCode($warehouse->type),
            externalTypeRef: $warehouse->type->value,
            number: $warehouse->number,
            name: $warehouse->description,
            description: null,
            address: $warehouse->shortAddress,
            latitude: $warehouse->latitude,
            longitude: $warehouse->longitude,
            sourceCategory: $warehouse->category,
            sourceStatus: $warehouse->status,
            isActive: $isActive,
            isSelectable: $isSelectable,
            metadata: $this->mapMetadata($warehouse),
            schedules: $this->mapSchedules($warehouse->schedule),
        );
    }

    /**
     * @param list<NovaPoshtaWarehouseDto> $warehouses
     *
     * @return list<DeliveryPointSyncDto>
     */
    public function mapPoints(array $warehouses): array
    {
        if (!array_is_list($warehouses)) {
            throw new InvalidArgumentException(
                'Nova Poshta warehouses must be provided as a list.'
            );
        }

        $result = [];

        foreach ($warehouses as $warehouse) {
            if (!$warehouse instanceof NovaPoshtaWarehouseDto) {
                throw new InvalidArgumentException(
                    'Nova Poshta warehouses list contains an invalid object.'
                );
            }

            $result[] = $this->mapPoint($warehouse);
        }

        return $result;
    }

    /**
     * Преобразует недельное расписание конкретного отделения
     * в нормализованные строки delivery_point_schedule.
     *
     * Один день может содержать несколько интервалов:
     *
     * 08:00-13:00; 14:00-18:00
     *
     * @return list<DeliveryPointScheduleSyncDto>
     */
    public function mapSchedules(
        ?NovaPoshtaWeeklyScheduleDto $schedule
    ): array {
        if ($schedule === null) {
            return [];
        }

        $result = [];

        foreach (
            $schedule->intervalsByWeekday()
            as $weekday => $rawValue
        ) {
            foreach (
                $this->mapWeekdayIntervals(
                    weekday: $weekday,
                    rawValue: $rawValue
                )
                as $interval
            ) {
                $result[] = $interval;
            }
        }

        return $result;
    }

    private function mapPointTypeCode(
        NovaPoshtaWarehouseType $type
    ): string {
        return match ($type) {
            NovaPoshtaWarehouseType::POST_OFFICE
            => self::POINT_TYPE_POST_OFFICE,

            NovaPoshtaWarehouseType::CARGO_BRANCH
            => self::POINT_TYPE_CARGO_BRANCH,
        };
    }

    /**
     * @return list<DeliveryPointScheduleSyncDto>
     */
    private function mapWeekdayIntervals(
        int $weekday,
        string $rawValue
    ): array {
        $value = $this->normalizeScheduleValue($rawValue);

        if ($value === '') {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta schedule for weekday %d must not be empty.',
                $weekday
            ));
        }

        if ($this->isClosedMarker($value)) {
            return [
                new DeliveryPointScheduleSyncDto(
                    weekday: $weekday,
                    intervalNo: 1,
                    isClosed: true,
                ),
            ];
        }

        $pattern = '~(?<opens>(?:[01]\d|2[0-3]):[0-5]\d)'
            . '\s*-\s*'
            . '(?<closes>(?:[01]\d|2[0-3]):[0-5]\d)~u';

        $matchCount = preg_match_all(
            $pattern,
            $value,
            $matches,
            PREG_SET_ORDER
        );

        if ($matchCount === false || $matchCount === 0) {
            throw new UnexpectedValueException(sprintf(
                'Unsupported Nova Poshta schedule value "%s" for weekday %d.',
                $rawValue,
                $weekday
            ));
        }

        $remainder = preg_replace(
            $pattern,
            '',
            $value
        );

        if ($remainder === null) {
            throw new UnexpectedValueException(
                'Unable to validate Nova Poshta schedule value.'
            );
        }

        $remainder = preg_replace(
            '~[\s,;|/]+~u',
            '',
            $remainder
        );

        if ($remainder === null || $remainder !== '') {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta schedule value "%s" contains unsupported content.',
                $rawValue
            ));
        }

        $result = [];
        $intervalNo = 1;

        foreach ($matches as $match) {
            $opensAt = (string)$match['opens'];
            $closesAt = (string)$match['closes'];

            if ($opensAt >= $closesAt) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta schedule interval "%s-%s" for weekday %d is invalid.',
                    $opensAt,
                    $closesAt,
                    $weekday
                ));
            }

            $result[] = new DeliveryPointScheduleSyncDto(
                weekday: $weekday,
                intervalNo: $intervalNo,
                opensAt: $opensAt . ':00',
                closesAt: $closesAt . ':00',
                isClosed: false,
            );

            $intervalNo++;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMetadata(
        NovaPoshtaWarehouseDto $warehouse
    ): array {
        return [
            'novaPoshta' => $this->removeNullValues([
                'settlementName' => $warehouse->settlementName,
                'areaName' => $this->nullableText($warehouse->areaName),
                'regionName' => $warehouse->regionName,
                'denyToSelect' => $warehouse->denyToSelect,

                'totalMaxWeightAllowed'
                => $warehouse->totalMaxWeightAllowed,

                'placeMaxWeightAllowed'
                => $warehouse->placeMaxWeightAllowed,

                'sendingDimensions'
                => $this->mapDimensions(
                    $warehouse->sendingDimensions
                ),

                'receivingDimensions'
                => $this->mapDimensions(
                    $warehouse->receivingDimensions
                ),

                /*
                 * Основной Schedule дополнительно сохраняется как raw snapshot,
                 * хотя его нормализованная версия записывается в schedules.
                 */
                'schedule'
                => $this->mapScheduleMetadata(
                    $warehouse->schedule
                ),

                /*
                 * Reception и Delivery не используются checkout как
                 * публичный график работы, но сохраняются без потери данных.
                 */
                'receptionSchedule'
                => $this->mapScheduleMetadata(
                    $warehouse->receptionSchedule
                ),

                'deliverySchedule'
                => $this->mapScheduleMetadata(
                    $warehouse->deliverySchedule
                ),
            ]),
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private function mapDimensions(
        ?NovaPoshtaDimensionsDto $dimensions
    ): ?array {
        if ($dimensions === null) {
            return null;
        }

        return [
            'width' => $dimensions->width,
            'height' => $dimensions->height,
            'length' => $dimensions->length,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function mapScheduleMetadata(
        ?NovaPoshtaWeeklyScheduleDto $schedule
    ): ?array {
        if ($schedule === null) {
            return null;
        }

        $result = [];

        foreach (
            $schedule->intervalsByWeekday()
            as $weekday => $interval
        ) {
            $result[$this->weekdayName($weekday)] = $interval;
        }

        return $result !== []
            ? $result
            : null;
    }

    private function weekdayName(int $weekday): string
    {
        return match ($weekday) {
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',

            default => throw new UnexpectedValueException(sprintf(
                'Unsupported ISO weekday "%d".',
                $weekday
            )),
        };
    }

    private function normalizeScheduleValue(
        string $value
    ): string {
        $value = trim($value);

        $value = str_replace(
            ['–', '—'],
            '-',
            $value
        );

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;
    }

    private function isClosedMarker(string $value): bool
    {
        $normalized = mb_strtolower(
            trim($value),
            'UTF-8'
        );

        return in_array(
            $normalized,
            self::CLOSED_MARKERS,
            true
        );
    }

    private function nullableText(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Удаляет только null.
     *
     * false и 0 являются значимыми provider values
     * и должны сохраняться.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function removeNullValues(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null
        );
    }
}