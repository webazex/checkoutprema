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
use yii\db\Expression;

final readonly class DeliveryPointStorage implements DeliveryPointStorageInterface
{
    public function __construct(private DeliveryProviderStorage $providers, private DeliveryReadMapper $mapper)
    {
    }

    public function findById(int $id): ?DeliveryPointReadDto
    {
        $model = $this->baseQuery()->byId($id)->one();

        return $model === null ? null : $this->mapper->mapPoint($model);
    }

    private function baseQuery(): DeliveryPointQuery
    {
        $query = DeliveryPointModel::find();

        $query->with(['provider', 'settlement.area', 'type', 'schedules',]);

        return $query;
    }

    public function findSelectableByProviderAndExternalRef(string $providerCode, string $externalRef): ?DeliveryPointReadDto
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $model = $this->baseQuery()->byProviderAndExternalRef($provider->id, $externalRef)->availableForCheckout()->one();

        return $model === null ? null : $this->mapper->mapPoint($model);
    }

    public function getSelectableById(int $id): DeliveryPointReadDto
    {
        return $this->findSelectableById($id) ?? throw new OutOfBoundsException(sprintf('Selectable delivery point #%d was not found.', $id));
    }

    public function findSelectableById(int $id): ?DeliveryPointReadDto
    {
        $model = $this->baseQuery()->byId($id)->availableForCheckout()->one();

        if ($model === null || !$this->hasActiveProvider($model)) {
            return null;
        }

        return $this->mapper->mapPoint($model);
    }

    private function hasActiveProvider(DeliveryPointModel $model): bool
    {
        if (!$model->isRelationPopulated('provider')) {
            return false;
        }

        $provider = $model->getRelatedRecords()['provider'] ?? null;

        return $provider instanceof DeliveryProviderModel && $provider->getIsActive();
    }

    /**
     * @return list<DeliveryPointReadDto>
     */
    public function findSelectableBySettlement(string $providerCode, int $settlementId): array
    {
        $provider = $this->providers->getActiveByCode($providerCode);

        $models = $this->baseQuery()->byProviderId($provider->id)->bySettlementId($settlementId)->availableForCheckout()->orderedByNumber()->all();

        return array_map(fn(DeliveryPointModel $model): DeliveryPointReadDto => $this->mapper->mapPoint($model), $models);
    }

    /**
     * @return list<DeliveryPointReadDto>
     */
    public function searchSelectableByNumber(
        string $providerCode,
        string $numberQuery,
        ?int $areaId,
        int $limit
    ): array {
        $provider = $this->providers->getActiveByCode($providerCode);

        $numberQuery = trim($numberQuery);

        if ($numberQuery === '') {
            return [];
        }

        $limit = max(1, min($limit, 30));

        $query = DeliveryPointModel::find()
            ->alias('point')
            ->joinWith(['settlement settlement'], false)
            ->andWhere(['point.provider_id' => $provider->id])
            ->andWhere(['point.is_active' => true])
            ->andWhere(['point.is_selectable' => true])
            ->andWhere(['point.archived_at' => null])
            ->andWhere(['settlement.archived_at' => null])
            ->andWhere(['like', 'point.number', $numberQuery])
            ->with(['provider', 'settlement.area', 'type', 'schedules'])
            ->addParams([
                ':exactNumber' => $numberQuery,
                ':prefixNumber' => $numberQuery . '%',
            ])
            ->orderBy(new Expression(
                'CASE
                WHEN [[point]].[[number]] = :exactNumber THEN 0
                WHEN [[point]].[[number]] LIKE :prefixNumber THEN 1
                ELSE 2
            END ASC,
            [[settlement]].[[name]] ASC,
            [[point]].[[number]] ASC,
            [[point]].[[id]] ASC'
            ))
            ->limit($limit);

        if ($areaId !== null) {
            $query->andWhere(['settlement.area_id' => $areaId]);
        }

        $models = $query->all();

        return array_map(
            fn(DeliveryPointModel $model): DeliveryPointReadDto
            => $this->mapper->mapPoint($model),
            $models
        );
    }
}