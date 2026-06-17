<?php

declare(strict_types=1);

namespace common\services\novaposhta;

use common\integrations\novaposhta\NovaPoshtaApiClient;
use InvalidArgumentException;

final class NovaPoshtaApiService
{
    private const MODEL_ADDRESS = 'Address';

    private const METHOD_SEARCH_SETTLEMENTS = 'searchSettlements';
    private const METHOD_GET_WAREHOUSES = 'getWarehouses';
    private const METHOD_GET_WAREHOUSE_TYPES = 'getWarehouseTypes';

    /**
     * Обычное почтовое отделение.
     *
     * Исключаются:
     * - Parcel Shop;
     * - грузовые отделения;
     * - почтоматы;
     * - почтоматы ПриватБанка.
     */
    private const WAREHOUSE_TYPE_POST_OFFICE_REF = '841339c7-591a-42e2-8233-7a0a00f0ed6f';
    private const WAREHOUSE_TYPE_CARGO_BRANCH_REF = '9a68df70-0267-42a8-bb5c-37f427e36ee4';

    private const DEFAULT_SETTLEMENT_LIMIT = 20;
    private const MAX_SETTLEMENT_LIMIT = 100;

    private const DEFAULT_WAREHOUSE_PAGE = 1;
    private const DEFAULT_WAREHOUSE_LIMIT = 500;
    private const MAX_WAREHOUSE_LIMIT = 500;

    public function __construct(private readonly NovaPoshtaApiClient $apiClient)
    {
    }

    /**
     * Получает только обычные почтовые отделения выбранного города.
     *
     * В $deliveryCityRef передаётся DeliveryCity из результата
     * searchSettlements().
     *
     * Возвращает сырой ответ Nova Poshta API.
     *
     * @return array<string, mixed>
     */


    public function getPostOffices(string $deliveryCityRef, ?int $page = null, ?int $limit = null): array
    {
        return $this->getWarehousesByType(
            $deliveryCityRef,
            self::WAREHOUSE_TYPE_POST_OFFICE_REF,
            $page,
            $limit
        );
    }

    public function getCargoBranches(string $deliveryCityRef, ?int $page = null, ?int $limit = null): array
    {
        return $this->getWarehousesByType(
            $deliveryCityRef,
            self::WAREHOUSE_TYPE_CARGO_BRANCH_REF,
            $page,
            $limit
        );
    }

    private function getWarehousesByType(
        string $deliveryCityRef,
        string $warehouseTypeRef,
        ?int $page = null,
        ?int $limit = null
    ): array {
        $deliveryCityRef = trim($deliveryCityRef);

        if ($deliveryCityRef === '') {
            throw new InvalidArgumentException(
                'Nova Poshta delivery city reference must not be empty.'
            );
        }

        return $this->apiClient->call(
            modelName: self::MODEL_ADDRESS,
            calledMethod: self::METHOD_GET_WAREHOUSES,
            methodProperties: [
                'CityRef' => $deliveryCityRef,
                'TypeOfWarehouseRef' => $warehouseTypeRef,
                'Page' => $this->normalizePage($page),
                'Limit' => $this->normalizeLimit(
                    $limit,
                    self::DEFAULT_WAREHOUSE_LIMIT,
                    self::MAX_WAREHOUSE_LIMIT
                ),
            ],
        );
    }


    /**
     * Возвращает сырой ответ Nova Poshta API.
     *
     * @return array<string, mixed>
     */
    public function searchSettlements(string $query, ?int $limit = null): array
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('Settlement search query must not be empty.');
        }

        return $this->apiClient->call(
            modelName: self::MODEL_ADDRESS,
            calledMethod: self::METHOD_SEARCH_SETTLEMENTS,
            methodProperties: [
                'CityName' => $query,
                'Limit' => $this->normalizeLimit(
                    value: $limit,
                    default: self::DEFAULT_SETTLEMENT_LIMIT,
                    maximum: self::MAX_SETTLEMENT_LIMIT,
                ),
            ],
        );
    }
    /**
     * Диагностический метод получения справочника типов точек.
     *
     * @return array<string, mixed>
     */
    public function getWarehouseTypes(): array
    {
        return $this->apiClient->call(
            modelName: self::MODEL_ADDRESS,
            calledMethod: self::METHOD_GET_WAREHOUSE_TYPES,
        );
    }

    private function normalizePage(?int $page): int
    {
        return $page === null
            ? self::DEFAULT_WAREHOUSE_PAGE
            : max(1, $page);
    }

    private function normalizeLimit(?int $value, int $default, int $maximum): int
    {
        return $value === null
            ? $default
            : max(1, min($value, $maximum));
    }
}