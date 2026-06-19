<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliveryPointTypeReadDto;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliveryPointTypeModel;
use OutOfBoundsException;

final readonly class DeliveryPointTypeStorage
{
    public function __construct(
        private DeliveryReadMapper $mapper
    ) {
    }

    /**
     * @return list<DeliveryPointTypeReadDto>
     */
    public function findAll(): array
    {
        $models = DeliveryPointTypeModel::find()
            ->ordered()
            ->all();

        return array_map(
            fn (
                DeliveryPointTypeModel $model
            ): DeliveryPointTypeReadDto => $this->mapper->mapPointType($model),
            $models
        );
    }

    public function findByCode(
        string $code
    ): ?DeliveryPointTypeReadDto {
        $model = DeliveryPointTypeModel::find()
            ->byCode($code)
            ->one();

        return $model === null
            ? null
            : $this->mapper->mapPointType($model);
    }

    public function getByCode(
        string $code
    ): DeliveryPointTypeReadDto {
        return $this->findByCode($code)
            ?? throw new OutOfBoundsException(sprintf(
                'Delivery point type "%s" was not found.',
                $code
            ));
    }
}