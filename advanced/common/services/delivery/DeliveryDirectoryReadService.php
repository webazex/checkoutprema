<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\dto\delivery\DeliveryAreaReadDto;
use common\dto\delivery\DeliveryPointReadDto;
use common\dto\delivery\DeliveryPointScheduleReadDto;
use common\dto\delivery\DeliveryProviderReadDto;
use common\dto\delivery\DeliverySettlementReadDto;
use common\storages\delivery\DeliveryAreaStorage;
use common\storages\delivery\DeliveryPointStorage;
use common\storages\delivery\DeliveryProviderStorage;
use common\storages\delivery\DeliverySettlementStorage;
use InvalidArgumentException;
use OutOfBoundsException;

final readonly class DeliveryDirectoryReadService
{
    public function __construct(
        private DeliveryProviderStorage $providers,
        private DeliveryAreaStorage $areas,
        private DeliverySettlementStorage $settlements,
        private DeliveryPointStorage $points,
        private DeliveryDirectoryCacheService $cache,
    ) {
    }

    /**
     * @return list<DeliveryProviderReadDto>
     */
    public function providers(): array
    {
        return $this->cache->rememberProviders(
            fn (): array => $this->providers->findActive()
        );
    }

    /**
     * @return list<DeliveryAreaReadDto>
     */
    public function areas(string $providerCode): array
    {
        $provider = $this->resolveProvider($providerCode);

        return $this->cache->rememberAreas(
            $provider->code,
            fn (): array => $this->areas->findAvailableByProviderCode(
                $provider->code
            )
        );
    }

    /**
     * @return list<DeliverySettlementReadDto>
     */
    public function settlements(string $providerCode, int $areaId): array
    {
        $provider = $this->resolveProvider($providerCode);

        $this->areas->getAvailableByProviderAndId(
            $provider->code,
            $areaId
        );

        return $this->cache->rememberSettlements(
            $provider->code,
            $areaId,
            fn (): array => $this->settlements->findAvailableByArea(
                $provider->code,
                $areaId
            )
        );
    }

    /**
     * @return list<DeliveryPointReadDto>
     */
    public function points(string $providerCode, int $settlementId): array
    {
        $provider = $this->resolveProvider($providerCode);

        $this->settlements->getAvailableByProviderAndId(
            $provider->code,
            $settlementId
        );

        return $this->cache->rememberPoints(
            $provider->code,
            $settlementId,
            fn (): array => $this->points->findSelectableBySettlement(
                $provider->code,
                $settlementId
            )
        );
    }

    /**
     * @return list<DeliveryPointScheduleReadDto>
     */
    public function pointSchedules(
        string $providerCode,
        int $pointId
    ): array {
        $provider = $this->resolveProvider($providerCode);

        return $this->cache->rememberPointSchedules(
            $provider->code,
            $pointId,
            function () use ($provider, $pointId): array {
                $point = $this->points->getSelectableById($pointId);

                if ($point->providerCode !== $provider->code) {
                    throw new OutOfBoundsException(sprintf(
                        'Selectable delivery point "%s:%d" was not found.',
                        $provider->code,
                        $pointId
                    ));
                }

                return $point->schedules;
            }
        );
    }

    private function resolveProvider(
        string $providerCode
    ): DeliveryProviderReadDto {
        $providerCode = strtolower(trim($providerCode));

        if ($providerCode === '') {
            throw new InvalidArgumentException(
                'Delivery provider code must not be empty.'
            );
        }

        foreach ($this->providers() as $provider) {
            if ($provider->code === $providerCode) {
                return $provider;
            }
        }

        throw new OutOfBoundsException(sprintf(
            'Active delivery provider "%s" was not found.',
            $providerCode
        ));
    }
}