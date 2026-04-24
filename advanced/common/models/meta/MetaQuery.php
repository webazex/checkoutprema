<?php

declare(strict_types=1);

namespace common\models\meta;

use yii\db\ActiveQuery;

final class MetaQuery extends ActiveQuery
{
    public function forEntity(string $entityType, int $entityId): self
    {
        return $this->andWhere([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    public function byKey(string $key): self
    {
        return $this->andWhere(['key' => $key]);
    }
}