<?php

declare(strict_types=1);

namespace common\models\delivery;

use yii\db\ActiveQuery;

/**
 * @method DeliveryPointModel|null one($db = null)
 * @method DeliveryPointModel[] all($db = null)
 */
final class DeliveryPointQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byProviderId(int $providerId): self
    {
        return $this->andWhere(['provider_id' => $providerId]);
    }

    public function bySettlementId(int $settlementId): self
    {
        return $this->andWhere(['settlement_id' => $settlementId]);
    }

    public function byTypeId(int $typeId): self
    {
        return $this->andWhere(['type_id' => $typeId]);
    }

    public function byExternalRef(string $externalRef): self
    {
        return $this->andWhere(['external_ref' => $externalRef]);
    }

    public function byProviderAndExternalRef(int $providerId, string $externalRef): self
    {
        return $this
            ->byProviderId($providerId)
            ->byExternalRef($externalRef);
    }

    public function byNumber(string $number): self
    {
        return $this->andWhere(['number' => $number]);
    }

    public function numberStartsWith(string $number): self
    {
        return $this->andWhere([
            'like',
            'number',
            $number . '%',
            false,
        ]);
    }

    public function numberContains(string $number): self
    {
        return $this->andWhere([
            'like',
            'number',
            $number,
        ]);
    }

    public function active(): self
    {
        return $this->andWhere(['is_active' => true]);
    }

    public function selectable(): self
    {
        return $this->andWhere(['is_selectable' => true]);
    }

    public function notArchived(): self
    {
        return $this->andWhere(['archived_at' => null]);
    }

    public function archived(): self
    {
        return $this->andWhere(['not', ['archived_at' => null]]);
    }

    public function availableForCheckout(): self
    {
        return $this
            ->active()
            ->selectable()
            ->notArchived();
    }

    public function withCoordinates(): self
    {
        return $this
            ->andWhere(['not', ['latitude' => null]])
            ->andWhere(['not', ['longitude' => null]]);
    }

    public function orderedByNumber(): self
    {
        return $this->orderBy([
            'number' => SORT_ASC,
            'id' => SORT_ASC,
        ]);
    }
}