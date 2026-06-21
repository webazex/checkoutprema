<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliveryAreaReadDto;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliveryAreaModel;
use OutOfBoundsException;

final readonly class DeliveryAreaStorage
{
    public function __construct(
        private DeliveryProviderStorage $providers,
        private DeliveryReadMapper      $mapper,
    )
    {
    }

    public function getAvailableByProviderAndId(
        string $providerCode,
        int    $id
    ): DeliveryAreaReadDto
    {
        return $this->findAvailableByProviderAndId($providerCode, $id)
            ?? throw new OutOfBoundsException(sprintf(
                'Available delivery area "%s:%d" was not found.',
                $providerCode,
                $id
            ));
    }

    public function findAvailableByProviderAndId(
        string $providerCode,
        int    $id
    ): ?DeliveryAreaReadDto
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = DeliveryAreaModel::find()
            ->byProviderId($provider->id)
            ->byId($id)
            ->notArchived()
            ->with('provider')
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapArea($model);
    }

    /**
     * @return list<DeliveryAreaReadDto>
     */
    public function findAvailableByProviderCode(
        string $providerCode
    ): array
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $models = DeliveryAreaModel::find()
            ->byProviderId($provider->id)
            ->notArchived()
            ->with('provider')
            ->orderedByName()
            ->all();

        return array_map(
            fn(
                DeliveryAreaModel $model
            ): DeliveryAreaReadDto => $this->mapper->mapArea($model),
            $models
        );
    }

    public function findById(
        int $id
    ): ?DeliveryAreaReadDto
    {
        $model = DeliveryAreaModel::find()
            ->byId($id)
            ->with('provider')
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapArea($model);
    }

    public function getAvailableByProviderAndExternalRef(
        string $providerCode,
        string $externalRef
    ): DeliveryAreaReadDto
    {
        return $this->findAvailableByProviderAndExternalRef(
            $providerCode,
            $externalRef
        ) ?? throw new OutOfBoundsException(sprintf(
            'Delivery area "%s:%s" was not found.',
            $providerCode,
            $externalRef
        ));
    }

    public function findAvailableByProviderAndExternalRef(
        string $providerCode,
        string $externalRef
    ): ?DeliveryAreaReadDto
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = DeliveryAreaModel::find()
            ->byProviderAndExternalRef(
                $provider->id,
                $externalRef
            )
            ->notArchived()
            ->with('provider')
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapArea($model);
    }
}