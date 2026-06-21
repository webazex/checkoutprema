<?php

declare(strict_types=1);

namespace common\traits;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

trait TimestampTrait
{
    public static function recent($query = null)
    {
        $query = $query ?? static::find();
        return $query->orderBy(['created_at' => SORT_DESC]);
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors() ?? [], [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => static function ($event): int {
                    return time();
                },
            ],
        ]);
    }

    public function getCreatedAtFormatted(string $format = 'd.m.Y H:i:s'): string
    {
        return $this->created_at ? date($format, (int)$this->created_at) : '';
    }

    public function getUpdatedAtFormatted(string $format = 'd.m.Y H:i:s'): string
    {
        return $this->updated_at ? date($format, (int)$this->updated_at) : '';
    }
}