<?php

namespace common\models\order;

use common\models\BaseModel;
use common\models\product\ProductModel;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string|null $wix_product_id
 * @property string $title
 * @property string|null $sku_snapshot
 * @property float $price
 * @property int $quantity
 * @property float $subtotal
 * @property string $currency
 * @property string|null $product_payload_snapshot
 * @property int $created_at
 * @property int $updated_at
 *
 * @property OrderModel $order
 * @property ProductModel|null $product
 */
class OrderItemModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%order_item}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['order_id', 'title', 'price', 'quantity', 'subtotal', 'currency'], 'required'],
            [['order_id', 'product_id', 'quantity', 'created_at', 'updated_at'], 'integer'],
            [['price', 'subtotal'], 'number'],
            [['product_payload_snapshot'], 'string'],
            [['title'], 'string', 'max' => 255],
            [['wix_product_id'], 'string', 'max' => 50],
            [['sku_snapshot'], 'string', 'max' => 64],
            [['currency'], 'string', 'max' => 3],

            [
                ['order_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => OrderModel::class,
                'targetAttribute' => ['order_id' => 'id'],
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
            'order_id' => 'Заказ',
            'product_id' => 'Товар',
            'wix_product_id' => 'Wix Product ID',
            'title' => 'Название',
            'sku_snapshot' => 'SKU snapshot',
            'price' => 'Цена',
            'quantity' => 'Количество',
            'subtotal' => 'Subtotal',
            'currency' => 'Валюта',
            'product_payload_snapshot' => 'Snapshot payload',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(OrderModel::class, ['id' => 'order_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(ProductModel::class, ['id' => 'product_id']);
    }
}