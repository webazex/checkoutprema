<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliveryProviderReadDto;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliveryProviderModel;
use OutOfBoundsException;

final readonly class DeliveryProviderStorage
{
    public function __construct(
        private DeliveryReadMapper $mapper
    )
    {
    }

    /**
     * @return list<DeliveryProviderReadDto>
     */
    public function findActive(): array
    {
        $models = DeliveryProviderModel::find()
            ->active()
            ->ordered()
            ->all();

        return array_map(
            fn(
                DeliveryProviderModel $model
            ): DeliveryProviderReadDto => $this->mapper->mapProvider($model),
            $models
        );
    }

    public function getByCode(
        string $code
    ): DeliveryProviderReadDto
    {
        return $this->findByCode($code)
            ?? throw new OutOfBoundsException(sprintf(
                'Delivery provider "%s" was not found.',
                $code
            ));
    }

    public function findByCode(
        string $code
    ): ?DeliveryProviderReadDto
    {
        $model = DeliveryProviderModel::find()
            ->byCode($code)
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapProvider($model);
    }

    public function getActiveByCode(
        string $code
    ): DeliveryProviderReadDto
    {
        return $this->findActiveByCode($code)
            ?? throw new OutOfBoundsException(sprintf(
                'Active delivery provider "%s" was not found.',
                $code
            ));
    }

    public function findActiveByCode(
        string $code
    ): ?DeliveryProviderReadDto
    {
        $model = DeliveryProviderModel::find()
            ->byCode($code)
            ->active()
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapProvider($model);
    }
}