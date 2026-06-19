<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliveryPointReadDto;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliveryPointModel;
use common\models\delivery\DeliveryPointQuery;
use common\models\delivery\DeliveryProviderModel;
use common\contracts\delivery\DeliveryPointStorageInterface;
use OutOfBoundsException;

final readonly class DeliveryPointStorage implements DeliveryPointStorageInterface
{
    public function __construct(
        private DeliveryProviderStorage $providers,
        private DeliveryReadMapper $mapper,
    ) {
    }

    public function findById(
        int $id
    ): ?DeliveryPointReadDto {
        $model = $this->baseQuery()
            ->byId($id)
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapPoint($model);
    }

    public function findSelectableById(
        int $id
    ): ?DeliveryPointReadDto {
        $model = $this->baseQuery()
            ->byId($id)
            ->availableForCheckout()
            ->one();

        if ($model === null || !$this->hasActiveProvider($model)) {
            return null;
        }

        return $this->mapper->mapPoint($model);
    }

    public function findSelectableByProviderAndExternalRef(
        string $providerCode,
        string $externalRef
    ): ?DeliveryPointReadDto {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = $this->baseQuery()
            ->byProviderAndExternalRef(
                $provider->id,
                $externalRef
            )
            ->availableForCheckout()
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapPoint($model);
    }

    public function getSelectableById(
        int $id
    ): DeliveryPointReadDto {
        return $this->findSelectableById($id)
            ?? throw new OutOfBoundsException(sprintf(
                'Selectable delivery point #%d was not found.',
                $id
            ));
    }

    private function baseQuery(): DeliveryPointQuery
    {
        $query = DeliveryPointModel::find();

        $query->with([
            'provider',
            'settlement.area',
            'type',
            'schedules',
        ]);

        return $query;
    }

    private function hasActiveProvider(
        DeliveryPointModel $model
    ): bool {
        if (!$model->isRelationPopulated('provider')) {
            return false;
        }

        $provider = $model->getRelatedRecords()['provider'] ?? null;

        return $provider instanceof DeliveryProviderModel
            && $provider->getIsActive();
    }
}