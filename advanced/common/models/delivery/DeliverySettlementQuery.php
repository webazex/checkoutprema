<?php

declare(strict_types=1);

namespace common\models\delivery;

use yii\db\ActiveQuery;

/**
 * @method DeliverySettlementModel|null one($db = null)
 * @method DeliverySettlementModel[] all($db = null)
 */
final class DeliverySettlementQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byProviderId(int $providerId): self
    {
        return $this->andWhere(['provider_id' => $providerId]);
    }

    public function byAreaId(int $areaId): self
    {
        return $this->andWhere(['area_id' => $areaId]);
    }

    public function byExternalRef(string $externalRef): self
    {
        return $this->andWhere(['external_ref' => $externalRef]);
    }

    public function byDeliveryRef(string $deliveryRef): self
    {
        return $this->andWhere(['delivery_ref' => $deliveryRef]);
    }

    public function byProviderAndExternalRef(int $providerId, string $externalRef): self
    {
        return $this
            ->byProviderId($providerId)
            ->byExternalRef($externalRef);
    }

    public function notArchived(): self
    {
        return $this->andWhere(['archived_at' => null]);
    }

    public function archived(): self
    {
        return $this->andWhere(['not', ['archived_at' => null]]);
    }

    public function searchName(string $query): self
    {
        return $this->andWhere([
            'like',
            'search_name',
            $query,
        ]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([
            'name' => SORT_ASC,
            'id' => SORT_ASC,
        ]);
    }
}