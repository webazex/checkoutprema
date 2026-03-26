<?php


namespace common\models;

use yii\db\ActiveRecord;
use common\traits\TimestampTrait;

/**
 * Базовая модель для всех сущностей проекта
 * (Customer, Product, Order, OrderItem, Meta, Payment и т.д.)
 */
abstract class BaseModel extends ActiveRecord
{
    use TimestampTrait;

    /**
     * Общие правила, которые должны быть у всех моделей
     */
    public function rules(): array
    {
        return array_merge(parent::rules() ?? [], [
            [['created_at', 'updated_at'], 'safe'],
        ]);
    }

    /**
     * Можно добавить сюда общие методы, которые нужны всем моделям
     * (например, форматирование дат, статусов и т.д.)
     */
}