<?php

declare(strict_types=1);

namespace common\models\product;

use yii\db\ActiveQuery;

final class ProductExternalMapQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byIds(array $ids): self
    {
        return $this->andWhere(['id' => $ids]);
    }

    public function forProduct(int $productId): self
    {
        return $this->andWhere(['product_id' => $productId]);
    }

    public function wix(): self
    {
        return $this->source(ProductExternalMapModel::SOURCE_WIX);
    }

    public function source(string $externalSource): self
    {
        return $this->andWhere(['external_source' => $externalSource]);
    }

    public function prom(): self
    {
        return $this->source(ProductExternalMapModel::SOURCE_PROM);
    }

    public function keycrm(): self
    {
        return $this->source(ProductExternalMapModel::SOURCE_KEYCRM);
    }

    public function byExternalId(string $externalId): self
    {
        return $this->andWhere(['external_id' => $externalId]);
    }

    public function bySourceAndExternalId(string $externalSource, string $externalId): self
    {
        return $this->andWhere([
            'external_source' => $externalSource,
            'external_id' => $externalId,
        ]);
    }

    public function bySkuSnapshot(string $sku): self
    {
        return $this->andWhere(['sku_snapshot' => $sku]);
    }

    public function newestFirst(): self
    {
        return $this->orderBy(['id' => SORT_DESC]);
    }

    public function createdDesc(): self
    {
        return $this->orderBy([
            'created_at' => SORT_DESC,
            'id' => SORT_DESC,
        ]);
    }
}