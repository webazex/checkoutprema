<?php

namespace common\models\search;

use yii\base\Model;
use common\traits\SearchableTrait;

/**
 * Абстрактный класс для всех поисковых моделей
 */
abstract class BaseSearch extends Model
{
    use SearchableTrait;

    // общий атрибут для диапазона дат (можно использовать во всех формах поиска)
    public $created_at_range;

    public function rules(): array
    {
        return [
            [['created_at_range'], 'safe'],
        ];
    }
}