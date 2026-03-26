<?php

namespace common\models\customer;

use yii\db\ActiveQuery;

class CustomerQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['status' => CustomerModel::STATUS_ACTIVE]);
    }

    public function blocked(): self
    {
        return $this->andWhere(['status' => CustomerModel::STATUS_BLOCKED]);
    }

    public function inactive(): self
    {
        return $this->andWhere(['status' => CustomerModel::STATUS_INACTIVE]);
    }

    public function byEmail(string $email): self
    {
        return $this->andWhere(['email' => mb_strtolower(trim($email))]);
    }

    public function byHash(string $hash): self
    {
        return $this->andWhere(['hash' => trim($hash)]);
    }
}