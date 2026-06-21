<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliverySettlementReadDto;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliverySettlementModel;
use OutOfBoundsException;

final readonly class DeliverySettlementStorage
{
    public function __construct(private DeliveryProviderStorage $providers, private DeliveryReadMapper $mapper)
    {
    }

    public function getAvailableByProviderAndId(string $providerCode, int $id): DeliverySettlementReadDto
    {
        return $this->findAvailableByProviderAndId($providerCode, $id) ?? throw new OutOfBoundsException(sprintf('Available delivery settlement "%s:%d" was not found.', $providerCode, $id));
    }

    public function findAvailableByProviderAndId(string $providerCode, int $id): ?DeliverySettlementReadDto
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = DeliverySettlementModel::find()->byProviderId($provider->id)->byId($id)->notArchived()->with(['provider', 'area',])->one();

        return $model === null ? null : $this->mapper->mapSettlement($model);
    }

    /**
     * @return list<DeliverySettlementReadDto>
     */
    public function findAvailableByArea(string $providerCode, int $areaId): array
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $models = DeliverySettlementModel::find()->byProviderId($provider->id)->byAreaId($areaId)->notArchived()->with(['provider', 'area',])->orderedByName()->all();

        return array_map(fn(DeliverySettlementModel $model): DeliverySettlementReadDto => $this->mapper->mapSettlement($model), $models);
    }

    public function findById(int $id): ?DeliverySettlementReadDto
    {
        $model = DeliverySettlementModel::find()->byId($id)->with(['provider', 'area',])->one();

        return $model === null ? null : $this->mapper->mapSettlement($model);
    }

    public function findAvailableByProviderAndDeliveryRef(string $providerCode, string $deliveryRef): ?DeliverySettlementReadDto
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = DeliverySettlementModel::find()->byProviderId($provider->id)->byDeliveryRef($deliveryRef)->notArchived()->with(['provider', 'area',])->one();

        return $model === null ? null : $this->mapper->mapSettlement($model);
    }

    public function getAvailableByProviderAndExternalRef(string $providerCode, string $externalRef): DeliverySettlementReadDto
    {
        return $this->findAvailableByProviderAndExternalRef($providerCode, $externalRef) ?? throw new OutOfBoundsException(sprintf('Delivery settlement "%s:%s" was not found.', $providerCode, $externalRef));
    }


    public function findAvailableByProviderAndExternalRef(string $providerCode, string $externalRef): ?DeliverySettlementReadDto
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = DeliverySettlementModel::find()->byProviderAndExternalRef($provider->id, $externalRef)->notArchived()->with(['provider', 'area',])->one();

        return $model === null ? null : $this->mapper->mapSettlement($model);
    }
}