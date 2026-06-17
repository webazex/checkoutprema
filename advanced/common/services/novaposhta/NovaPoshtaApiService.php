<?php

declare(strict_types=1);

namespace common\services\novaposhta;

use common\enums\novaposhta\NovaPoshtaWarehouseType;
use common\integrations\novaposhta\NovaPoshtaApiClient;
use InvalidArgumentException;

final class NovaPoshtaApiService
{
    public const MAX_WAREHOUSE_LIMIT = 500;

    private const MODEL_ADDRESS = 'Address';

    private const METHOD_SEARCH_SETTLEMENTS = 'searchSettlements';
    private const METHOD_GET_WAREHOUSES = 'getWarehouses';
    private const METHOD_GET_WAREHOUSE_TYPES = 'getWarehouseTypes';

    private const DEFAULT_SETTLEMENT_LIMIT = 20;
    private const MAX_SETTLEMENT_LIMIT = 100;

    private const DEFAULT_WAREHOUSE_PAGE = 1;
    private const DEFAULT_WAREHOUSE_LIMIT = self::MAX_WAREHOUSE_LIMIT;

    public function __construct(private readonly NovaPoshtaApiClient $apiClient)
    {
    }

    /**
     * Возвращает сырой ответ Nova Poshta API
     * с обычными почтовыми отделениями выбранного города.
     *
     * @return array<string, mixed>
     */
    public function getPostOffices(string $deliveryCityRef, ?int $page = null, ?int $limit = null): array
    {
        return $this->getWarehouses(
            $deliveryCityRef,
            NovaPoshtaWarehouseType::POST_OFFICE,
            $page,
            $limit
        );
    }

    /**
     * Возвращает сырой ответ Nova Poshta API
     * со складами грузового типа выбранного города.
     *
     * Ответ может содержать не только клиентские отделения,
     * но и другие категории, например Fulfillment.
     *
     * @return array<string, mixed>
     */
    public function getCargoBranches(string $deliveryCityRef, ?int $page = null, ?int $limit = null): array
    {
        return $this->getWarehouses(
            $deliveryCityRef,
            NovaPoshtaWarehouseType::CARGO_BRANCH,
            $page,
            $limit
        );
    }

    /**
     * Возвращает сырой ответ getWarehouses
     * для одного типа склада.
     *
     * @return array<string, mixed>
     */
    public function getWarehouses(
        string $deliveryCityRef,
        NovaPoshtaWarehouseType $type,
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
                'TypeOfWarehouseRef' => $type->value,
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
            throw new InvalidArgumentException(
                'Settlement search query must not be empty.'
            );
        }

        return $this->apiClient->call(
            modelName: self::MODEL_ADDRESS,
            calledMethod: self::METHOD_SEARCH_SETTLEMENTS,
            methodProperties: [
                'CityName' => $query,
                'Limit' => $this->normalizeLimit(
                    $limit,
                    self::DEFAULT_SETTLEMENT_LIMIT,
                    self::MAX_SETTLEMENT_LIMIT
                ),
            ],
        );
    }

    /**
     * Диагностический метод получения справочника типов складов.
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