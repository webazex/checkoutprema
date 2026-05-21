<?php

declare(strict_types=1);

namespace common\models\catalog;

use yii\db\ActiveQuery;

final class CatalogCategoryQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this
            ->andWhere(['is_active' => 1])
            ->andWhere(['is_archived' => 0]);
    }

    public function archived(): self
    {
        return $this->andWhere(['is_archived' => 1]);
    }

    public function bySlug(string $slug): self
    {
        return $this->andWhere(['slug' => $slug]);
    }

    public function byExternal(string $source, string $externalId): self
    {
        return $this->andWhere([
            'external_source' => $source,
            'external_id' => $externalId,
        ]);
    }

    public function roots(): self
    {
        return $this->andWhere(['parent_id' => null]);
    }

    public function ordered(): self
    {
        return $this->orderBy([
            'sort_order' => SORT_ASC,
            'id' => SORT_ASC,
        ]);
    }
}