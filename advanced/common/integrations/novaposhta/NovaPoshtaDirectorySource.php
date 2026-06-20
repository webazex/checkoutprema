<?php

declare(strict_types=1);

namespace common\integrations\novaposhta;

use common\contracts\delivery\DeliveryDirectorySourceInterface;
use common\dto\delivery\DeliverySyncPageDto;
use common\dto\novaposhta\NovaPoshtaWarehouseDto;
use common\enums\novaposhta\NovaPoshtaWarehouseType;
use common\mappers\novaposhta\NovaPoshtaDirectoryMapper;
use common\mappers\novaposhta\NovaPoshtaResponseMapper;
use common\services\novaposhta\NovaPoshtaApiService;
use InvalidArgumentException;
use UnexpectedValueException;
use common\dto\delivery\DeliveryAreaSyncDto;
use common\dto\novaposhta\NovaPoshtaAreaDto;
use common\dto\novaposhta\NovaPoshtaSettlementDto;

final class NovaPoshtaDirectorySource implements DeliveryDirectorySourceInterface
{
    private const DEFAULT_LIMIT = 500;
    private const MAX_LIMIT = 500;

    private const BRANCH_CATEGORY = 'Branch';

    private const CURSOR_TYPE_POST_OFFICE = 'post_office';
    private const CURSOR_TYPE_CARGO_BRANCH = 'cargo_branch';

    /** * @var list<DeliveryAreaSyncDto>|null */
    private ?array $areaCache = null;

    public function __construct(
        private readonly NovaPoshtaApiService      $apiService,
        private readonly NovaPoshtaResponseMapper  $responseMapper,
        private readonly NovaPoshtaDirectoryMapper $directoryMapper,
    )
    {
    }

    public function fetchAreas(?string $cursor = null, ?int $limit = null): DeliverySyncPageDto
    {
        $limit = $this->normalizeLimit($limit);
        $offset = $this->decodeOffsetCursor($cursor, 'areas');
        $areas = $this->loadCanonicalAreas();
        $totalCount = count($areas);
        $items = array_slice($areas, $offset, $limit);
        $nextOffset = $offset + count($items);
        return new DeliverySyncPageDto(
            items: $items,
            nextCursor: $nextOffset < $totalCount ? (string)$nextOffset : null,
            totalCount: $totalCount,
        );
    }

    public function fetchSettlements(
        ?string $areaExternalRef = null,
        ?string $cursor = null,
        ?int    $limit = null
    ): DeliverySyncPageDto
    {
        $areaExternalRef = $this->normalizeOptionalRef(
            $areaExternalRef,
            'areaExternalRef'
        );

        $limit = $this->normalizeLimit($limit);
        $page = $this->decodePageCursor(
            $cursor,
            'settlements'
        );

        $providerPage = $this->responseMapper->mapSettlementPage(
            response: $this->apiService->getSettlements(
                page: $page,
                limit: $limit
            ),
            page: $page,
            limit: $limit,
        );

        $providerSettlements = $providerPage->items;

        if ($areaExternalRef !== null) {
            $providerSettlements = array_values(array_filter(
                $providerSettlements,
                static fn($settlement): bool => $settlement->areaRef === $areaExternalRef
            ));
        }

        return new DeliverySyncPageDto(
            items: $this->directoryMapper->mapSettlements(
                $providerSettlements
            ),
            nextCursor: $providerPage->hasMore()
                ? (string)($page + 1)
                : null,
            totalCount: $areaExternalRef === null
                ? $providerPage->apiTotalCount
                : null,
        );
    }

    public function fetchPoints(
        ?string $settlementDeliveryRef = null,
        ?string $cursor = null,
        ?int    $limit = null
    ): DeliverySyncPageDto
    {
        $settlementDeliveryRef = $this->normalizeOptionalRef(
            $settlementDeliveryRef,
            'settlementDeliveryRef'
        );

        $limit = $this->normalizeLimit($limit);

        [$type, $page] = $this->decodePointCursor(
            $cursor
        );

        $response = $settlementDeliveryRef === null
            ? $this->apiService->getGlobalWarehouses(
                type: $type,
                page: $page,
                limit: $limit
            )
            : $this->apiService->getWarehouses(
                deliveryCityRef: $settlementDeliveryRef,
                type: $type,
                page: $page,
                limit: $limit
            );

        $providerPage = $this->responseMapper->mapWarehousePage(
            response: $response,
            page: $page,
            limit: $limit,
        );

        $warehouses = $this->filterBranchWarehouses(
            $providerPage->items,
            $type
        );

        return new DeliverySyncPageDto(
            items: $this->directoryMapper->mapPoints(
                $warehouses
            ),
            nextCursor: $this->resolveNextPointCursor(
                $type,
                $page,
                $providerPage->hasMore()
            ),
            /*
             * API totalCount относится к одному warehouse type
             * и включает Store/DropOff. Для объединённого filtered
             * потока точного totalCount здесь нет.
             */
            totalCount: null,
        );
    }


    /** * @return list<DeliveryAreaSyncDto> */
    private function loadCanonicalAreas(): array
    {
        if ($this->areaCache !== null) {
            return $this->areaCache;
        }
        $directoryAreas = $this->responseMapper->mapAreas($this->apiService->getAreas());
        $directoryAreasByName = $this->indexDirectoryAreasByName($directoryAreas);
        $areasByRef = [];
        $areaRefsByName = [];
        $page = 1;
        do {
            $settlementPage = $this->responseMapper->mapSettlementPage($this->apiService->getSettlements($page, self::MAX_LIMIT), $page, self::MAX_LIMIT);
            foreach ($settlementPage->items as $settlement) {
                $areaName = $this->requiredSettlementAreaName($settlement);
                $nameKey = $this->normalizeAreaName($areaName);
                $existingRef = $areaRefsByName[$nameKey] ?? null;
                if ($existingRef !== null && $existingRef !== $settlement->areaRef) {
                    throw new UnexpectedValueException(sprintf('Nova Poshta area name "%s" is linked to conflicting references "%s" and "%s".', $areaName, $existingRef, $settlement->areaRef));
                }
                if (isset($areasByRef[$settlement->areaRef])) {
                    $existingArea = $areasByRef[$settlement->areaRef];
                    if ($this->normalizeAreaName($existingArea->name) !== $nameKey) {
                        throw new UnexpectedValueException(sprintf('Nova Poshta area reference "%s" is linked to conflicting names "%s" and "%s".', $settlement->areaRef, $existingArea->name, $areaName));
                    }
                    continue;
                }
                $areasByRef[$settlement->areaRef] = $this->directoryMapper->mapAreaFromSettlement($settlement, $directoryAreasByName[$nameKey] ?? null);
                $areaRefsByName[$nameKey] = $settlement->areaRef;
            }
            $page++;
        } while ($settlementPage->hasMore());
        if ($areasByRef === []) {
            throw new UnexpectedValueException('Nova Poshta settlements did not provide any areas.');
        }
        $areas = array_values($areasByRef);
        usort($areas, fn(DeliveryAreaSyncDto $left, DeliveryAreaSyncDto $right): int => $this->normalizeAreaName($left->name) <=> $this->normalizeAreaName($right->name));
        return $this->areaCache = $areas;
    }

    /** * @param list<NovaPoshtaAreaDto> $areas * * @return array<string, NovaPoshtaAreaDto> */
    private function indexDirectoryAreasByName(array $areas): array
    {
        $result = [];
        foreach ($areas as $area) {
            $nameKey = $this->normalizeAreaName($area->name);
            if (isset($result[$nameKey])) {
                throw new UnexpectedValueException(sprintf('Nova Poshta getAreas returned duplicate area name "%s".', $area->name));
            }
            $result[$nameKey] = $area;
        }
        return $result;
    }

    private function requiredSettlementAreaName(NovaPoshtaSettlementDto $settlement): string
    {
        $areaName = trim((string)$settlement->areaName);
        if ($areaName === '') {
            throw new UnexpectedValueException(sprintf('Nova Poshta settlement "%s" does not contain an area name.', $settlement->ref));
        }
        return $areaName;
    }

    private function normalizeAreaName(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /**
     * @param list<NovaPoshtaWarehouseDto> $warehouses
     *
     * @return list<NovaPoshtaWarehouseDto>
     */
    private function filterBranchWarehouses(
        array                   $warehouses,
        NovaPoshtaWarehouseType $requestedType
    ): array
    {
        $result = [];
        $seenRefs = [];

        foreach ($warehouses as $warehouse) {
            if ($warehouse->type !== $requestedType) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta returned warehouse type "%s" while "%s" was requested.',
                    $warehouse->type->name,
                    $requestedType->name
                ));
            }

            if ($warehouse->category !== self::BRANCH_CATEGORY) {
                continue;
            }

            if (isset($seenRefs[$warehouse->ref])) {
                throw new UnexpectedValueException(sprintf(
                    'Nova Poshta returned duplicate warehouse "%s" in one page.',
                    $warehouse->ref
                ));
            }

            $seenRefs[$warehouse->ref] = true;
            $result[] = $warehouse;
        }

        return $result;
    }

    private function resolveNextPointCursor(
        NovaPoshtaWarehouseType $type,
        int                     $page,
        bool                    $hasMore
    ): ?string
    {
        if ($hasMore) {
            return $this->encodePointCursor(
                $type,
                $page + 1
            );
        }

        return match ($type) {
            NovaPoshtaWarehouseType::POST_OFFICE =>
            $this->encodePointCursor(
                NovaPoshtaWarehouseType::CARGO_BRANCH,
                1
            ),

            NovaPoshtaWarehouseType::CARGO_BRANCH => null,
        };
    }

    /**
     * @return array{NovaPoshtaWarehouseType, int}
     */
    private function decodePointCursor(?string $cursor): array
    {
        if ($cursor === null) {
            return [
                NovaPoshtaWarehouseType::POST_OFFICE,
                1,
            ];
        }

        $cursor = trim($cursor);

        if (
            preg_match(
                '/^(post_office|cargo_branch):([1-9]\d*)\z/',
                $cursor,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid Nova Poshta point cursor "%s".',
                $cursor
            ));
        }

        $type = match ($matches[1]) {
            self::CURSOR_TYPE_POST_OFFICE =>
            NovaPoshtaWarehouseType::POST_OFFICE,

            self::CURSOR_TYPE_CARGO_BRANCH =>
            NovaPoshtaWarehouseType::CARGO_BRANCH,
        };

        return [
            $type,
            (int)$matches[2],
        ];
    }

    private function encodePointCursor(
        NovaPoshtaWarehouseType $type,
        int                     $page
    ): string
    {
        $typeCode = match ($type) {
            NovaPoshtaWarehouseType::POST_OFFICE =>
            self::CURSOR_TYPE_POST_OFFICE,

            NovaPoshtaWarehouseType::CARGO_BRANCH =>
            self::CURSOR_TYPE_CARGO_BRANCH,
        };

        return $typeCode . ':' . $page;
    }

    private function decodeOffsetCursor(
        ?string $cursor,
        string  $scope
    ): int
    {
        if ($cursor === null) {
            return 0;
        }

        $cursor = trim($cursor);

        if (
            preg_match(
                '/^(?:0|[1-9]\d*)\z/',
                $cursor
            ) !== 1
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid Nova Poshta %s cursor "%s".',
                $scope,
                $cursor
            ));
        }

        return (int)$cursor;
    }

    private function decodePageCursor(
        ?string $cursor,
        string  $scope
    ): int
    {
        if ($cursor === null) {
            return 1;
        }

        $cursor = trim($cursor);

        if (
            preg_match(
                '/^[1-9]\d*\z/',
                $cursor
            ) !== 1
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid Nova Poshta %s cursor "%s".',
                $scope,
                $cursor
            ));
        }

        return (int)$cursor;
    }

    private function normalizeOptionalRef(
        ?string $value,
        string  $field
    ): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(sprintf(
                'Nova Poshta %s must not be empty.',
                $field
            ));
        }

        return $value;
    }

    private function normalizeLimit(?int $limit): int
    {
        if ($limit === null) {
            return self::DEFAULT_LIMIT;
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Nova Poshta directory limit must be greater than zero.'
            );
        }

        return min(
            $limit,
            self::MAX_LIMIT
        );
    }
}