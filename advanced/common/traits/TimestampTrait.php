<?php

namespace common\traits;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Трейт для автоматического заполнения created_at / updated_at + удобные форматтеры
 */
trait TimestampTrait
{
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                // храним как unix timestamp (integer) — самый надёжный и удобный вариант
                'value' => time(...),
            ],
        ];
    }

    public function getCreatedAtFormatted(string $format = 'd.m.Y H:i:s'): string
    {
        return $this->created_at ? date($format, $this->created_at) : '';
    }

    public function getUpdatedAtFormatted(string $format = 'd.m.Y H:i:s'): string
    {
        return $this->updated_at ? date($format, $this->updated_at) : '';
    }

    // Полезные скоупы (можно расширять)
    public static function recent($query = null)
    {
        $query = $query ?? static::find();
        return $query->orderBy(['created_at' => SORT_DESC]);
    }
}