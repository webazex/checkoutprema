<?php

namespace common\models\cart;

use common\models\BaseModel;
use common\models\product\ProductModel;

/**
 * @property int $id
 * @property int $cart_id
 * @property int|null $product_id
 * @property string $title
 * @property string|null $sku_snapshot
 * @property float $price
 * @property int $quantity
 * @property float $subtotal
 * @property string $currency
 * @property int $created_at
 * @property int $updated_at
 *
 * @property CartModel $cart
 * @property ProductModel|null $product
 */
class CartItemModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%cart_item}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['cart_id', 'title', 'price', 'quantity', 'subtotal', 'currency'], 'required'],
            [['cart_id', 'product_id', 'quantity', 'created_at', 'updated_at'], 'integer'],
            [['price', 'subtotal'], 'number'],
            [['title'], 'string', 'max' => 255],
            [['sku_snapshot'], 'string', 'max' => 64],
            [['currency'], 'string', 'max' => 3],

            [
                ['cart_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => CartModel::class,
                'targetAttribute' => ['cart_id' => 'id'],
            ],
            [
                ['product_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => ProductModel::class,
                'targetAttribute' => ['product_id' => 'id'],
            ],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'cart_id' => 'Корзина',
            'product_id' => 'Товар',
            'title' => 'Название',
            'sku_snapshot' => 'SKU snapshot',
            'price' => 'Цена',
            'quantity' => 'Количество',
            'subtotal' => 'Subtotal',
            'currency' => 'Валюта',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getCart()
    {
        return $this->hasOne(CartModel::class, ['id' => 'cart_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(ProductModel::class, ['id' => 'product_id']);
    }
}