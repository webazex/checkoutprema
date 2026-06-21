<?php

namespace common\traits;

use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * Общая логика для всех Search-моделей (CustomerSearch, OrderSearch и т.д.)
 */
trait SearchableTrait
{
    /**
     * Базовый метод search — вызывается из контроллера
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = $this->getBaseQuery();

        $this->load($params);

        if (!$this->validate()) {
            // если валидация не прошла — возвращаем пустой провайдер
            return new ActiveDataProvider([
                'query' => $query,
                'sort' => $this->getDefaultSort(),
                'pagination' => $this->getDefaultPagination(),
            ]);
        }

        // общие фильтры (id, диапазон дат и т.д.)
        $this->applyCommonFilters($query);

        // конкретные фильтры модели — переопределяются в наследнике
        $this->applyFilters($query);

        return new ActiveDataProvider([
            'query' => $query,
            'sort' => $this->getDefaultSort(),
            'pagination' => $this->getDefaultPagination(),
        ]);
    }

    /**
     * Базовый query — переопределяется в каждой Search-модели
     */
    protected function getBaseQuery(): ActiveQuery
    {
        return static::find();
    }

    /**
     * Стандартная сортировка по умолчанию
     */
    protected function getDefaultSort(): array
    {
        return ['defaultOrder' => ['created_at' => SORT_DESC]];
    }

    /**
     * Стандартная пагинация
     */
    protected function getDefaultPagination(): array
    {
        return ['pageSize' => 20];
    }

    /**
     * Общие фильтры, которые есть почти всегда
     */
    protected function applyCommonFilters(ActiveQuery $query): void
    {
        $query->andFilterWhere(['id' => $this->id ?? null]);

        // пример: диапазон created_at (удобно для фильтров типа date-range-picker)
        if (isset($this->created_at_range) && is_string($this->created_at_range)) {
            $range = explode(' - ', $this->created_at_range);
            if (count($range) === 2) {
                $query->andFilterWhere(['between', 'created_at',
                    strtotime($range[0]), strtotime($range[1]) + 86399]); // до конца дня
            }
        }
    }

    /**
     * Конкретные фильтры — переопределяется в каждой Search-модели
     */
    protected function applyFilters(ActiveQuery $query): void
    {
        // пустой по умолчанию
    }
}