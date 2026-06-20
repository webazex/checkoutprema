<?php

declare(strict_types=1);

namespace common\mappers\novaposhta;

use common\dto\novaposhta\NovaPoshtaDimensionsDto;
use common\dto\novaposhta\NovaPoshtaWarehouseDto;
use common\dto\novaposhta\NovaPoshtaWarehousePageDto;
use common\enums\novaposhta\NovaPoshtaWarehouseType;
use common\dto\novaposhta\NovaPoshtaWeeklyScheduleDto;
use common\dto\novaposhta\NovaPoshtaAreaDto;
use common\dto\novaposhta\NovaPoshtaSettlementDto;
use common\dto\novaposhta\NovaPoshtaSettlementPageDto;
use InvalidArgumentException;
use UnexpectedValueException;
use yii\helpers\Json;

final class NovaPoshtaResponseMapper
{
    private const SCHEDULE_DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];
    /**
     * Преобразует полный сырой ответ getWarehouses
     * в типизированную страницу складов Nova Poshta.
     *
     * В $page и $limit должны передаваться фактические значения,
     * использованные при API-запросе.
     *
     * @param array<string, mixed> $response
     */
    public function mapWarehousePage(array $response, int $page, int $limit): NovaPoshtaWarehousePageDto
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Nova Poshta warehouse page must be greater than zero.');
        }

        if ($limit < 1) {
            throw new InvalidArgumentException('Nova Poshta warehouse limit must be greater than zero.');
        }

        $this->assertSuccessfulResponse($response);

        $data = $response['data'] ?? null;

        if (!is_array($data) || !array_is_list($data)) {
            throw new UnexpectedValueException(
                'Nova Poshta warehouse response field "data" must be a list.'
            );
        }

        $items = [];

        foreach ($data as $index => $warehouseData) {
            if (!is_array($warehouseData)) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta warehouse record at index %d must be an object.',
                    $index
                ));
            }

            $items[] = $this->mapWarehouse($warehouseData, $index);
        }

        return new NovaPoshtaWarehousePageDto(
            items: $items,
            apiTotalCount: $this->extractTotalCount(
                $response,
                'warehouse'
            ),
            page: $page,
            limit: $limit
        );
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<NovaPoshtaAreaDto>
     */
    public function mapAreas(array $response): array
    {
        $this->assertSuccessfulResponse($response);

        $data = $this->extractDataList(
            $response,
            'area'
        );

        $result = [];

        foreach ($data as $index => $areaData) {
            $result[] = new NovaPoshtaAreaDto(
                ref: $this->requiredString(
                    $areaData,
                    'Ref',
                    $index
                ),
                name: $this->requiredString(
                    $areaData,
                    'Description',
                    $index
                ),
                centerRef: $this->optionalString(
                    $areaData,
                    'AreasCenter'
                ),
                nameRu: $this->optionalString(
                    $areaData,
                    'DescriptionRu'
                ),
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapSettlementPage(
        array $response,
        int $page,
        int $limit
    ): NovaPoshtaSettlementPageDto {
        if ($page < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta settlement page must be greater than zero.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta settlement limit must be greater than zero.'
            );
        }

        $this->assertSuccessfulResponse($response);

        $data = $this->extractDataList(
            $response,
            'settlement'
        );

        $items = [];

        foreach ($data as $index => $settlementData) {
            $items[] = $this->mapSettlement(
                $settlementData,
                $index
            );
        }

        return new NovaPoshtaSettlementPageDto(
            items: $items,
            apiTotalCount: $this->extractTotalCount(
                $response,
                'settlement'
            ),
            page: $page,
            limit: $limit,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapWarehouse(array $data, int $index): NovaPoshtaWarehouseDto {
        $description = $this->optionalString(
            $data,
            'Description'
        );

        $shortAddress = $this->optionalString(
            $data,
            'ShortAddress'
        );

        if ($description === null && $shortAddress === null) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d has neither description nor short address.',
                $index
            ));
        }

        $description ??= $shortAddress;
        $shortAddress ??= $description;

        return new NovaPoshtaWarehouseDto(
            ref: $this->requiredString(
                $data,
                'Ref',
                $index
            ),
            number: $this->requiredString(
                $data,
                'Number',
                $index
            ),
            type: $this->mapWarehouseType(
                $data,
                $index
            ),
            category: $this->requiredString(
                $data,
                'CategoryOfWarehouse',
                $index
            ),
            description: $description,
            shortAddress: $shortAddress,
            cityRef: $this->requiredString(
                $data,
                'CityRef',
                $index
            ),
            settlementRef: $this->requiredString(
                $data,
                'SettlementRef',
                $index
            ),
            settlementName: $this->requiredString(
                $data,
                'SettlementDescription',
                $index
            ),
            areaName: $this->optionalString(
            $data,
            'SettlementAreaDescription'
        ) ?? '',
            regionName: $this->optionalString(
                $data,
                'SettlementRegionsDescription'
            ),
            latitude: $this->optionalFloat(
                $data,
                'Latitude'
            ),
            longitude: $this->optionalFloat(
                $data,
                'Longitude'
            ),
            status: $this->requiredString(
                $data,
                'WarehouseStatus',
                $index
            ),
            denyToSelect: $this->requiredBooleanFlag(
                $data,
                'DenyToSelect',
                $index
            ),
            totalMaxWeightAllowed: $this->optionalFloat(
                $data,
                'TotalMaxWeightAllowed'
            ),
            placeMaxWeightAllowed: $this->optionalFloat(
                $data,
                'PlaceMaxWeightAllowed'
            ),
            sendingDimensions: $this->mapDimensions(
                $data['SendingLimitationsOnDimensions'] ?? null
            ),
            receivingDimensions: $this->mapDimensions(
                $data['ReceivingLimitationsOnDimensions'] ?? null
            ),
            schedule: $this->mapWeeklySchedule(
                $data['Schedule'] ?? null,
                'Schedule',
                $index
            ),
            receptionSchedule: $this->mapWeeklySchedule(
                $data['Reception'] ?? null,
                'Reception',
                $index
            ),
            deliverySchedule: $this->mapWeeklySchedule(
                $data['Delivery'] ?? null,
                'Delivery',
                $index
            ),
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertSuccessfulResponse(array $response): void
    {
        if (($response['success'] ?? null) !== true) {
            throw new UnexpectedValueException(
                'Nova Poshta response is not marked as successful.'
            );
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractTotalCount(
        array $response,
        string $context
    ): int {
        $info = $response['info'] ?? null;

        if (!is_array($info)) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta %s response field "info" must be an object.',
                $context
            ));
        }

        $totalCount = $info['totalCount'] ?? null;

        if (
            (!is_int($totalCount) && !is_string($totalCount))
            || !is_numeric($totalCount)
            || (int)$totalCount < 0
        ) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta %s response field "info.totalCount" must be a non-negative integer.',
                $context
            ));
        }

        return (int)$totalCount;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapWarehouseType(array $data, int $index): NovaPoshtaWarehouseType
    {
        $typeRef = $this->requiredString($data, 'TypeOfWarehouse', $index);
        $type = NovaPoshtaWarehouseType::tryFrom($typeRef);

        if ($type === null) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d contains unsupported warehouse type "%s".',
                $index,
                $typeRef
            ));
        }

        return $type;
    }

    private function mapDimensions(mixed $value): ?NovaPoshtaDimensionsDto
    {
        if ($value === null || $value === []) {
            return null;
        }

        if (!is_array($value)) {
            return null;
        }

        $width = $this->positiveIntOrNull($value['Width'] ?? null);
        $height = $this->positiveIntOrNull($value['Height'] ?? null);
        $length = $this->positiveIntOrNull($value['Length'] ?? null);

        if ($width === null || $height === null || $length === null) {
            return null;
        }

        return new NovaPoshtaDimensionsDto($width, $height, $length);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $key, int $index): string
    {
        $value = $data[$key] ?? null;

        if (!is_scalar($value)) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d is missing required field "%s".',
                $index,
                $key
            ));
        }

        $value = $this->normalizeText((string)$value);

        if ($value === '') {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d contains empty required field "%s".',
                $index,
                $key
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (!is_scalar($value)) {
            return null;
        }

        $value = $this->normalizeText((string)$value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float)$value;

        return is_finite($number) ? $number : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredBooleanFlag(array $data, string $key, int $index): bool
    {
        $value = $data[$key] ?? null;

        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw new UnexpectedValueException(sprintf(
            'Nova Poshta record at index %d contains invalid boolean flag "%s".',
            $index,
            $key
        ));
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (int)$value;

        return $number > 0 ? $number : null;
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return preg_replace('/\s*,\s*/u', ', ', $value) ?? $value;
    }

    private function mapWeeklySchedule(
        mixed $value,
        string $field,
        int $index
    ): ?NovaPoshtaWeeklyScheduleDto {
        if ($value === null || $value === []) {
            return null;
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d field "%s" must be an object.',
                $index,
                $field
            ));
        }

        $unknownDays = array_diff(
            array_keys($value),
            self::SCHEDULE_DAYS
        );

        if ($unknownDays !== []) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d field "%s" contains unsupported keys: %s.',
                $index,
                $field,
                implode(
                    ', ',
                    array_map(
                        static fn (mixed $day): string => (string)$day,
                        $unknownDays
                    )
                )
            ));
        }

        $intervals = [];

        foreach (self::SCHEDULE_DAYS as $day) {
            $rawInterval = $value[$day] ?? null;

            if ($rawInterval === null || $rawInterval === '') {
                $intervals[$day] = null;

                continue;
            }

            if (!is_scalar($rawInterval)) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta warehouse record at index %d field "%s.%s" must be a scalar value.',
                    $index,
                    $field,
                    $day
                ));
            }

            $interval = $this->normalizeText(
                (string)$rawInterval
            );

            $intervals[$day] = $interval !== ''
                ? $interval
                : null;
        }

        $schedule = new NovaPoshtaWeeklyScheduleDto(
            monday: $intervals['Monday'],
            tuesday: $intervals['Tuesday'],
            wednesday: $intervals['Wednesday'],
            thursday: $intervals['Thursday'],
            friday: $intervals['Friday'],
            saturday: $intervals['Saturday'],
            sunday: $intervals['Sunday'],
        );

        return $schedule->isEmpty()
            ? null
            : $schedule;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapSettlement(array $data, int $index): NovaPoshtaSettlementDto {
        return new NovaPoshtaSettlementDto(
            ref: $this->requiredString(
                $data,
                'Ref',
                $index
            ),
            areaRef: $this->requiredString(
                $data,
                'Area',
                $index
            ),
            name: $this->requiredString(
                $data,
                'Description',
                $index
            ),
            areaName: $this->optionalString(
                $data,
                'AreaDescription'
            ),
            regionRef: $this->optionalString(
                $data,
                'Region'
            ),
            regionName: $this->optionalString(
                $data,
                'RegionsDescription'
            ),
            settlementTypeRef: $this->optionalString(
                $data,
                'SettlementType'
            ),
            settlementTypeName: $this->optionalString(
                $data,
                'SettlementTypeDescription'
            ),
            latitude: $this->optionalFloat(
                $data,
                'Latitude'
            ),
            longitude: $this->optionalFloat(
                $data,
                'Longitude'
            ),
            hasWarehouse: $this->requiredBooleanFlag(
                $data,
                'Warehouse',
                $index
            ),
            addressDeliveryAllowed: $this->optionalBooleanFlag(
                $data,
                'AddressDeliveryAllowed',
                $index
            ),
            metadata: $this->mapSettlementMetadata(
                $data
            ),
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function mapSettlementMetadata(array $data): array
    {
        return $this->removeNullValues([
            'nameRu' => $this->optionalString(
                $data,
                'DescriptionRu'
            ),
            'nameTranslit' => $this->optionalString(
                $data,
                'DescriptionTranslit'
            ),
            'areaNameRu' => $this->optionalString(
                $data,
                'AreaDescriptionRu'
            ),
            'areaNameTranslit' => $this->optionalString(
                $data,
                'AreaDescriptionTranslit'
            ),
            'regionNameRu' => $this->optionalString(
                $data,
                'RegionsDescriptionRu'
            ),
            'regionNameTranslit' => $this->optionalString(
                $data,
                'RegionsDescriptionTranslit'
            ),
            'settlementTypeNameRu' => $this->optionalString(
                $data,
                'SettlementTypeDescriptionRu'
            ),
            'settlementTypeNameTranslit' => $this->optionalString(
                $data,
                'SettlementTypeDescriptionTranslit'
            ),
            'index1' => $this->optionalString(
                $data,
                'Index1'
            ),
            'index2' => $this->optionalString(
                $data,
                'Index2'
            ),
            'indexCoatsu1' => $this->optionalString(
                $data,
                'IndexCOATSU1'
            ),
            'deliveryDays' => $this->mapSettlementDeliveryDays(
                $data
            ),
            'radiusHomeDelivery' => $this->optionalFloat(
                $data,
                'RadiusHomeDelivery'
            ),
            'radiusExpressPickUp' => $this->optionalFloat(
                $data,
                'RadiusExpressPickUp'
            ),
            'radiusDrop' => $this->optionalFloat(
                $data,
                'RadiusDrop'
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, bool>
     */
    private function mapSettlementDeliveryDays(array $data): array
    {
        $result = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $value = $data['Delivery' . $weekday] ?? null;

            if ($value === null || $value === '') {
                $result[$weekday] = false;

                continue;
            }

            if ($value === true || $value === 1 || $value === '1') {
                $result[$weekday] = true;

                continue;
            }

            if ($value === false || $value === 0 || $value === '0') {
                $result[$weekday] = false;

                continue;
            }

            $result[$weekday] = false;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<array<string, mixed>>
     */
    private function extractDataList(
        array $response,
        string $context
    ): array {
        $data = $response['data'] ?? null;

        if (!is_array($data) || !array_is_list($data)) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta %s response field "data" must be a list.',
                $context
            ));
        }

        $result = [];

        foreach ($data as $index => $record) {
            if (!is_array($record)) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta %s record at index %d must be an object.',
                    $context,
                    $index
                ));
            }

            $result[] = $record;
        }

        return $result;
    }

    /**
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
    private function optionalBooleanFlag(array $data, string $key, int $index, bool $default = false): bool
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        $value = $data[$key];

        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value), 'UTF-8');

            if ($normalized === '') {
                return $default;
            }

            if ($normalized === 'true') {
                return true;
            }

            if ($normalized === 'false') {
                return false;
            }
        }

        throw new UnexpectedValueException(sprintf(
            'Nova Poshta record at index %d contains invalid boolean flag "%s" with value %s.',
            $index,
            $key,
            Json::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
    }


}