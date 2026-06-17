<?php


declare(strict_types=1);

namespace common\services\novaposhta;

use common\dto\novaposhta\NovaPoshtaDeliveryPointDto;
use common\dto\novaposhta\NovaPoshtaWarehouseDto;
use common\enums\novaposhta\NovaPoshtaWarehouseType;
use common\mappers\novaposhta\NovaPoshtaResponseMapper;
use UnexpectedValueException;

final class NovaPoshtaDeliveryService
{
    private const SELECTABLE_CATEGORY = 'Branch';
    private const SELECTABLE_STATUS = 'Working';

    public function __construct(
        private readonly NovaPoshtaApiService     $apiService,
        private readonly NovaPoshtaResponseMapper $responseMapper
    )
    {
    }

    /**
     * Возвращает все доступные для checkout точки доставки
     * выбранного населённого пункта.
     *
     * @return list<NovaPoshtaDeliveryPointDto>
     */
    public function getSelectableDeliveryPoints(string $deliveryCityRef): array
    {
        /** @var array<string, NovaPoshtaDeliveryPointDto> $pointsByRef */
        $pointsByRef = [];

        foreach (NovaPoshtaWarehouseType::cases() as $type) {
            foreach ($this->loadAllWarehouses($deliveryCityRef, $type) as $warehouse) {
                if (!$this->isSelectable($warehouse)) {
                    continue;
                }

                if (isset($pointsByRef[$warehouse->ref])) {
                    continue;
                }

                $pointsByRef[$warehouse->ref] = $this->mapDeliveryPoint($warehouse);
            }
        }

        return array_values($pointsByRef);
    }

    /**
     * Загружает все страницы одного типа склада.
     *
     * @return list<NovaPoshtaWarehouseDto>
     */
    private function loadAllWarehouses(
        string                  $deliveryCityRef,
        NovaPoshtaWarehouseType $type
    ): array
    {
        $page = 1;
        $limit = NovaPoshtaApiService::MAX_WAREHOUSE_LIMIT;
        $warehouses = [];

        while (true) {
            $response = $this->apiService->getWarehouses(
                $deliveryCityRef,
                $type,
                $page,
                $limit
            );

            $mappedPage = $this->responseMapper->mapWarehousePage(
                $response,
                $page,
                $limit
            );

            foreach ($mappedPage->items as $warehouse) {
                $warehouses[] = $warehouse;
            }

            if (!$mappedPage->hasMore()) {
                break;
            }

            if ($mappedPage->items === []) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta returned an empty warehouse page %d before the end of the result set.',
                    $page
                ));
            }

            $page++;
        }

        return $warehouses;
    }

    private function isSelectable(NovaPoshtaWarehouseDto $warehouse): bool
    {
        return $warehouse->category === self::SELECTABLE_CATEGORY
            && $warehouse->status === self::SELECTABLE_STATUS
            && !$warehouse->denyToSelect;
    }

    private function mapDeliveryPoint(NovaPoshtaWarehouseDto $warehouse): NovaPoshtaDeliveryPointDto
    {
        return new NovaPoshtaDeliveryPointDto(
            ref: $warehouse->ref,
            number: $warehouse->number,
            type: $warehouse->type,
            description: $warehouse->description,
            shortAddress: $warehouse->shortAddress,
            cityRef: $warehouse->cityRef,
            settlementRef: $warehouse->settlementRef,
            settlementName: $warehouse->settlementName,
            areaName: $warehouse->areaName,
            regionName: $warehouse->regionName,
            latitude: $warehouse->latitude,
            longitude: $warehouse->longitude,
            totalMaxWeightAllowed: $warehouse->totalMaxWeightAllowed,
            placeMaxWeightAllowed: $warehouse->placeMaxWeightAllowed,
            sendingDimensions: $warehouse->sendingDimensions,
            receivingDimensions: $warehouse->receivingDimensions
        );
    }
}