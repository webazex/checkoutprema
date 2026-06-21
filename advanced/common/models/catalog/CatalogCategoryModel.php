<?php

declare(strict_types=1);

namespace common\models\catalog;

use common\models\BaseModel;
use common\models\product\ProductModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property string $external_source
 * @property string $external_id
 * @property int|null $parent_id
 * @property string|null $parent_external_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $thumbnail_url
 * @property int $sort_order
 * @property int|bool $is_active
 * @property int|bool $is_archived
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property array|null $raw_payload
 * @property int $created_at
 * @property int $updated_at
 */
final class CatalogCategoryModel extends BaseModel
{
    public const SOURCE_KEYCRM = 'keycrm';

    public static function tableName(): string
    {
        return '{{%catalog_category}}';
    }

    public static function find(): CatalogCategoryQuery
    {
        return new CatalogCategoryQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['external_source', 'external_id', 'name', 'slug'], 'required'],

            [['parent_id', 'sort_order'], 'integer'],
            [['description', 'seo_description'], 'string'],
            [['raw_payload'], 'safe'],

            [['is_active', 'is_archived'], 'boolean'],

            [['external_source'], 'string', 'max' => 32],
            [['external_id', 'parent_external_id'], 'string', 'max' => 100],
            [['name', 'slug', 'seo_title'], 'string', 'max' => 255],
            [['thumbnail_url'], 'string', 'max' => 512],

            [['slug'], 'unique'],
            [['external_source', 'external_id'], 'unique', 'targetAttribute' => ['external_source', 'external_id']],

            [
                ['parent_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => self::class,
                'targetAttribute' => ['parent_id' => 'id'],
            ],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'external_source' => 'Источник',
            'external_id' => 'Внешний ID',
            'parent_id' => 'Родительская категория',
            'parent_external_id' => 'Внешний ID родителя',
            'name' => 'Название',
            'slug' => 'Slug',
            'description' => 'Описание',
            'thumbnail_url' => 'Изображение',
            'sort_order' => 'Сортировка',
            'is_active' => 'Активна',
            'is_archived' => 'В архиве',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'raw_payload' => 'Raw payload',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren(): ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(ProductModel::class, ['category_id' => 'id']);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->is_active;
    }

    public function getIsArchived(): bool
    {
        return (bool)$this->is_archived;
    }
}