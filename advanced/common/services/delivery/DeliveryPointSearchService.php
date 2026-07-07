<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\dto\delivery\DeliveryPointReadDto;
use common\dto\delivery\DeliveryPointSearchRequestDto;
use common\dto\delivery\DeliveryPointSearchResultDto;
use common\dto\delivery\DeliverySettlementReadDto;
use common\storages\delivery\DeliveryAreaStorage;
use common\storages\delivery\DeliveryPointStorage;
use common\storages\delivery\DeliverySettlementStorage;

final readonly class DeliveryPointSearchService
{
    public function __construct(
        private DeliveryDirectoryReadService $directory,
        private DeliveryAreaStorage $areas,
        private DeliverySettlementStorage $settlements,
        private DeliveryPointStorage $points,
    ) {
    }

    /**
     * @return list<DeliveryPointSearchResultDto>
     */
    public function search(DeliveryPointSearchRequestDto $request): array
    {
        if ($request->hasCityScope()) {
            return $this->searchInCityCache($request);
        }

        if ($request->hasAreaScope()) {
            return $this->searchInAreaDatabase($request);
        }

        return $this->searchInGlobalDatabase($request);
    }

    /**
     * @return list<DeliveryPointSearchResultDto>
     */
    private function searchInCityCache(
        DeliveryPointSearchRequestDto $request
    ): array {
        $settlement = $this->resolveSettlement($request);

        if ($settlement === null) {
            return [];
        }

        if (
            $request->areaRef !== null
            && $settlement->areaExternalRef !== $request->areaRef
        ) {
            return [];
        }

        $points = $this->directory->points(
            $request->providerCode,
            $settlement->id
        );

        $ranked = $this->rankInMemory(
            points: $points,
            query: $request->query
        );

        return array_slice(
            $this->mapPoints($ranked),
            0,
            $request->limit
        );
    }

    private function resolveSettlement(
        DeliveryPointSearchRequestDto $request
    ): ?DeliverySettlementReadDto {
        $settlement = null;

        if ($request->settlementRef !== null) {
            $settlement = $this->settlements
                ->findAvailableByProviderAndExternalRef(
                    $request->providerCode,
                    $request->settlementRef
                );
        }

        if ($settlement === null && $request->cityRef !== null) {
            $settlement = $this->settlements
                ->findAvailableByProviderAndDeliveryRef(
                    $request->providerCode,
                    $request->cityRef
                );
        }

        if (
            $settlement !== null
            && $request->cityRef !== null
            && $settlement->deliveryRef !== $request->cityRef
        ) {
            return null;
        }

        return $settlement;
    }

    /**
     * @param list<DeliveryPointReadDto> $points
     *
     * @return list<DeliveryPointReadDto>
     */
    private function rankInMemory(
        array $points,
        string $query
    ): array {
        $exact = [];
        $prefix = [];
        $contains = [];

        foreach ($points as $point) {
            $number = trim((string)$point->number);

            if ($number === '') {
                continue;
            }

            if ($number === $query) {
                $exact[] = $point;
                continue;
            }

            if (str_starts_with($number, $query)) {
                $prefix[] = $point;
                continue;
            }

            if (str_contains($number, $query)) {
                $contains[] = $point;
            }
        }

        return array_merge($exact, $prefix, $contains);
    }

    /**
     * @return list<DeliveryPointSearchResultDto>
     */
    private function searchInAreaDatabase(
        DeliveryPointSearchRequestDto $request
    ): array {
        $area = $this->areas->findAvailableByProviderAndExternalRef(
            $request->providerCode,
            (string)$request->areaRef
        );

        if ($area === null) {
            return [];
        }

        return $this->mapPoints(
            $this->points->searchSelectableByNumber(
                providerCode: $request->providerCode,
                numberQuery: $request->query,
                areaId: $area->id,
                limit: $request->limit
            )
        );
    }

    /**
     * @return list<DeliveryPointSearchResultDto>
     */
    private function searchInGlobalDatabase(
        DeliveryPointSearchRequestDto $request
    ): array {
        if (mb_strlen($request->query) < 2) {
            return [];
        }

        return $this->mapPoints(
            $this->points->searchSelectableByNumber(
                providerCode: $request->providerCode,
                numberQuery: $request->query,
                areaId: null,
                limit: $request->limit
            )
        );
    }

    /**
     * @param list<DeliveryPointReadDto> $points
     *
     * @return list<DeliveryPointSearchResultDto>
     */
    private function mapPoints(array $points): array
    {
        return array_map(
            fn(DeliveryPointReadDto $point): DeliveryPointSearchResultDto
            => new DeliveryPointSearchResultDto(
                point: $point,
                label: $this->buildLabel($point)
            ),
            $points
        );
    }

    private function buildLabel(DeliveryPointReadDto $point): string
    {
        if ($point->number === null || trim($point->number) === '') {
            return sprintf(
                '%s, %s — %s',
                $point->settlementName,
                $point->areaName,
                $point->address
            );
        }

        return sprintf(
            '№%s — %s, %s — %s',
            $point->number,
            $point->settlementName,
            $point->areaName,
            $point->address
        );
    }
}