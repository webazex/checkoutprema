<?php

namespace common\models\product;

use common\models\BaseModel;
use common\models\cart\CartItemModel;
use common\models\catalog\CatalogCategoryModel;
use common\models\order\OrderItemModel;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property float|null $purchased_price
 * @property int $quantity
 * @property float|null $weight_kg
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property string|null $thumbnail_url
 * @property string|null $images_json
 * @property int|null $category_external_id
 * @property int $is_archived
 * @property int|null $archived_at
 * @property int|null $category_id
 * @property int $created_at
 * @property int $updated_at
 *
 * @property ProductExternalMapModel[] $externalMaps
 * @property CatalogCategoryModel|null $category
 * @property CartItemModel[] $cartItems
 * @property OrderItemModel[] $orderItems
 */
class ProductModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%product}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['name', 'price', 'currency'], 'required'],
            [['description', 'images_json'], 'string'],
            [['price', 'purchased_price', 'weight_kg'], 'number'],
            [['quantity', 'length_mm', 'width_mm', 'height_mm', 'category_external_id', 'is_archived', 'created_at', 'updated_at'], 'integer'],
            [['name', 'slug'], 'string', 'max' => 255],
            [['sku', 'barcode'], 'string', 'max' => 64],
            [['currency'], 'string', 'max' => 3],
            [['thumbnail_url'], 'string', 'max' => 512],
            [['slug'], 'unique'],
            [['sku'], 'unique'],
            [['is_archived'], 'boolean'],
            [['archived_at'], 'integer'],
            [['category_id'], 'integer'],
            [
                ['category_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => CatalogCategoryModel::class,
                'targetAttribute' => ['category_id' => 'id'],
            ],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'slug' => 'Slug',
            'sku' => 'SKU',
            'barcode' => 'Штрихкод',
            'description' => 'Описание',
            'price' => 'Цена',
            'currency' => 'Валюта',
            'purchased_price' => 'Закупочная цена',
            'quantity' => 'Остаток',
            'weight_kg' => 'Вес (кг)',
            'length_mm' => 'Длина (мм)',
            'width_mm' => 'Ширина (мм)',
            'height_mm' => 'Высота (мм)',
            'thumbnail_url' => 'Изображение',
            'images_json' => 'Изображения',
            'category_external_id' => 'Внешняя категория',
            'is_archived' => 'В архиве',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
            'archived_at' => 'Архивировано',
            'category_id' => 'Категория',
        ];
    }

    public function getExternalMaps()
    {
        return $this->hasMany(ProductExternalMapModel::class, ['product_id' => 'id']);
    }

    public function getCategory()
    {
        return $this->hasOne(CatalogCategoryModel::class, ['id' => 'category_id']);
    }

    public function getCartItems()
    {
        return $this->hasMany(CartItemModel::class, ['product_id' => 'id']);
    }

    public function getOrderItems()
    {
        return $this->hasMany(OrderItemModel::class, ['product_id' => 'id']);
    }

    public function getIsArchived(): bool
    {
        return (bool)$this->is_archived;
    }

    public function getIsAvailable(): bool
    {
        return !$this->getIsArchived() && (int)$this->quantity > 0;
    }

    public static function find(): ProductQuery
    {
        return new ProductQuery(static::class);
    }
}