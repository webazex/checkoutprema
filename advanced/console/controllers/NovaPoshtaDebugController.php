<?php

declare(strict_types=1);

namespace console\controllers;

use common\dto\novaposhta\NovaPoshtaDimensionsDto;
use common\dto\novaposhta\NovaPoshtaWarehouseDto;
use common\dto\novaposhta\NovaPoshtaWarehousePageDto;
use common\mappers\novaposhta\NovaPoshtaResponseMapper;
use common\services\novaposhta\NovaPoshtaApiService;
use InvalidArgumentException;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

final class NovaPoshtaDebugController extends Controller
{
    private const MAX_WAREHOUSE_LIMIT = 500;

    /**
     * Возвращает полный сырой ответ поиска населённых пунктов.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-settlements "Одеса" 20
     */
    public function actionRawSettlements(string $query, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta raw settlements response',
            fn (): array => $this->getApiService()->searchSettlements($query, $limit)
        );
    }

    /**
     * Возвращает полный сырой ответ getWarehouses
     * для обычных почтовых отделений.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-post-offices <DELIVERY_CITY_REF> 1 20
     */
    public function actionRawPostOffices(string $deliveryCityRef, int $page = 1, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta raw post offices response',
            fn (): array => $this->getApiService()->getPostOffices($deliveryCityRef, $page, $limit)
        );
    }

    /**
     * Возвращает полный сырой ответ getWarehouses
     * для складов грузового типа.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-cargo-branches <DELIVERY_CITY_REF> 1 20
     */
    public function actionRawCargoBranches(string $deliveryCityRef, int $page = 1, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta raw cargo branches response',
            fn (): array => $this->getApiService()->getCargoBranches($deliveryCityRef, $page, $limit)
        );
    }

    /**
     * Возвращает обычные почтовые отделения,
     * преобразованные через NovaPoshtaResponseMapper.
     *
     * Пример:
     * php yii nova-poshta-debug/mapped-post-offices <DELIVERY_CITY_REF> 1 20
     */
    public function actionMappedPostOffices(string $deliveryCityRef, int $page = 1, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta mapped post offices response',
            function () use ($deliveryCityRef, $page, $limit): array {
                $this->validateWarehousePagination($page, $limit);

                $response = $this->getApiService()->getPostOffices(
                    $deliveryCityRef,
                    $page,
                    $limit
                );

                $mappedPage = $this->getResponseMapper()->mapWarehousePage(
                    $response,
                    $page,
                    $limit
                );

                return $this->warehousePageToArray($mappedPage);
            }
        );
    }

    /**
     * Возвращает склады грузового типа,
     * преобразованные через NovaPoshtaResponseMapper.
     *
     * Записи CategoryOfWarehouse = Fulfillment здесь
     * намеренно не удаляются: mapper отражает ответ API.
     *
     * Пример:
     * php yii nova-poshta-debug/mapped-cargo-branches <DELIVERY_CITY_REF> 1 20
     */
    public function actionMappedCargoBranches(string $deliveryCityRef, int $page = 1, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta mapped cargo branches response',
            function () use ($deliveryCityRef, $page, $limit): array {
                $this->validateWarehousePagination($page, $limit);

                $response = $this->getApiService()->getCargoBranches(
                    $deliveryCityRef,
                    $page,
                    $limit
                );

                $mappedPage = $this->getResponseMapper()->mapWarehousePage(
                    $response,
                    $page,
                    $limit
                );

                return $this->warehousePageToArray($mappedPage);
            }
        );
    }

    /**
     * Возвращает полный сырой справочник типов складов.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-warehouse-types
     */
    public function actionRawWarehouseTypes(): int
    {
        return $this->execute(
            'Nova Poshta raw warehouse types response',
            fn (): array => $this->getApiService()->getWarehouseTypes()
        );
    }

    /**
     * @param callable(): mixed $callback
     */
    private function execute(string $title, callable $callback): int
    {
        try {
            $result = $callback();

            $this->stdout($title . PHP_EOL . PHP_EOL);
            $this->stdout(
                Json::encode(
                    $result,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );

            return ExitCode::OK;
        } catch (Throwable $e) {
            $this->stderr(
                'Nova Poshta debug request failed: ' . $e->getMessage() . PHP_EOL
            );

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    private function validateWarehousePagination(int $page, int $limit): void
    {
        if ($page < 1) {
            throw new InvalidArgumentException(
                'Mapped warehouse page must be greater than zero.'
            );
        }

        if ($limit < 1 || $limit > self::MAX_WAREHOUSE_LIMIT) {
            throw new InvalidArgumentException(sprintf(
                'Mapped warehouse limit must be between 1 and %d.',
                self::MAX_WAREHOUSE_LIMIT
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function warehousePageToArray(NovaPoshtaWarehousePageDto $page): array
    {
        return [
            'items' => array_map(
                fn (NovaPoshtaWarehouseDto $warehouse): array
                => $this->warehouseToArray($warehouse),
                $page->items
            ),
            'apiTotalCount' => $page->apiTotalCount,
            'page' => $page->page,
            'limit' => $page->limit,
            'hasMore' => $page->hasMore(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function warehouseToArray(NovaPoshtaWarehouseDto $warehouse): array
    {
        return [
            'ref' => $warehouse->ref,
            'number' => $warehouse->number,
            'type' => $warehouse->type->name,
            'typeRef' => $warehouse->type->value,
            'category' => $warehouse->category,
            'description' => $warehouse->description,
            'shortAddress' => $warehouse->shortAddress,
            'cityRef' => $warehouse->cityRef,
            'settlementRef' => $warehouse->settlementRef,
            'settlementName' => $warehouse->settlementName,
            'areaName' => $warehouse->areaName,
            'regionName' => $warehouse->regionName,
            'latitude' => $warehouse->latitude,
            'longitude' => $warehouse->longitude,
            'status' => $warehouse->status,
            'denyToSelect' => $warehouse->denyToSelect,
            'totalMaxWeightAllowed' => $warehouse->totalMaxWeightAllowed,
            'placeMaxWeightAllowed' => $warehouse->placeMaxWeightAllowed,
            'sendingDimensions' => $this->dimensionsToArray(
                $warehouse->sendingDimensions
            ),
            'receivingDimensions' => $this->dimensionsToArray(
                $warehouse->receivingDimensions
            ),
        ];
    }

    /**
     * @return array{width: int, height: int, length: int}|null
     */
    private function dimensionsToArray(?NovaPoshtaDimensionsDto $dimensions): ?array
    {
        if ($dimensions === null) {
            return null;
        }

        return [
            'width' => $dimensions->width,
            'height' => $dimensions->height,
            'length' => $dimensions->length,
        ];
    }

    private function getApiService(): NovaPoshtaApiService
    {
        /** @var NovaPoshtaApiService $service */
        $service = Yii::$container->get(NovaPoshtaApiService::class);

        return $service;
    }

    private function getResponseMapper(): NovaPoshtaResponseMapper
    {
        /** @var NovaPoshtaResponseMapper $mapper */
        $mapper = Yii::$container->get(NovaPoshtaResponseMapper::class);

        return $mapper;
    }
}