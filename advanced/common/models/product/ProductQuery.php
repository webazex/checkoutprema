<?php

namespace common\models\product;

use yii\db\ActiveQuery;

class ProductQuery extends ActiveQuery
{
    public function notArchived(): self
    {
        return $this->andWhere(['is_archived' => 0]);
    }

    public function archived(): self
    {
        return $this->andWhere(['is_archived' => 1]);
    }

    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byIds(array $ids): self
    {
        return $this->andWhere(['id' => $ids]);
    }

    public function bySku(string $sku): self
    {
        return $this->andWhere(['sku' => $sku]);
    }

    public function bySlug(string $slug): self
    {
        return $this->andWhere(['slug' => $slug]);
    }

    public function ordered(): self
    {
        return $this->orderBy(['id' => SORT_DESC]);
    }
}