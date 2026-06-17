<?php

declare(strict_types=1);

namespace common\mappers\novaposhta;

use common\dto\novaposhta\NovaPoshtaDimensionsDto;
use common\dto\novaposhta\NovaPoshtaWarehouseDto;
use common\dto\novaposhta\NovaPoshtaWarehousePageDto;
use common\enums\novaposhta\NovaPoshtaWarehouseType;
use InvalidArgumentException;
use UnexpectedValueException;

final class NovaPoshtaResponseMapper
{
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
            apiTotalCount: $this->extractTotalCount($response),
            page: $page,
            limit: $limit
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapWarehouse(array $data, int $index): NovaPoshtaWarehouseDto
    {
        $description = $this->optionalString($data, 'Description');
        $shortAddress = $this->optionalString($data, 'ShortAddress');

        if ($description === null && $shortAddress === null) {
            throw new UnexpectedValueException(sprintf(
                'Nova Poshta warehouse record at index %d has neither description nor short address.',
                $index
            ));
        }

        $description ??= $shortAddress;
        $shortAddress ??= $description;

        return new NovaPoshtaWarehouseDto(
            ref: $this->requiredString($data, 'Ref', $index),
            number: $this->requiredString($data, 'Number', $index),
            type: $this->mapWarehouseType($data, $index),
            category: $this->requiredString($data, 'CategoryOfWarehouse', $index),
            description: $description,
            shortAddress: $shortAddress,
            cityRef: $this->requiredString($data, 'CityRef', $index),
            settlementRef: $this->requiredString($data, 'SettlementRef', $index),
            settlementName: $this->requiredString($data, 'SettlementDescription', $index),
            areaName: $this->optionalString($data, 'SettlementAreaDescription') ?? '',
            regionName: $this->optionalString($data, 'SettlementRegionsDescription'),
            latitude: $this->optionalFloat($data, 'Latitude'),
            longitude: $this->optionalFloat($data, 'Longitude'),
            status: $this->requiredString($data, 'WarehouseStatus', $index),
            denyToSelect: $this->requiredBooleanFlag($data, 'DenyToSelect', $index),
            totalMaxWeightAllowed: $this->optionalFloat($data, 'TotalMaxWeightAllowed'),
            placeMaxWeightAllowed: $this->optionalFloat($data, 'PlaceMaxWeightAllowed'),
            sendingDimensions: $this->mapDimensions($data['SendingLimitationsOnDimensions'] ?? null),
            receivingDimensions: $this->mapDimensions($data['ReceivingLimitationsOnDimensions'] ?? null)
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
    private function extractTotalCount(array $response): int
    {
        $info = $response['info'] ?? null;

        if (!is_array($info)) {
            throw new UnexpectedValueException(
                'Nova Poshta warehouse response field "info" must be an object.'
            );
        }

        $totalCount = $info['totalCount'] ?? null;

        if (
            (!is_int($totalCount) && !is_string($totalCount))
            || !is_numeric($totalCount)
            || (int)$totalCount < 0
        ) {
            throw new UnexpectedValueException(
                'Nova Poshta warehouse response field "info.totalCount" must be a non-negative integer.'
            );
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
            'Nova Poshta warehouse record at index %d contains invalid boolean flag "%s".',
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
}