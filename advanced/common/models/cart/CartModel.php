<?php

namespace common\models\cart;

use common\models\BaseModel;
use common\models\customer\CustomerModel;
use common\models\order\OrderModel;

/**
 * @property int $id
 * @property string $hash
 * @property int|null $customer_id
 * @property string|null $session_key
 * @property string $status
 * @property string $source_type
 * @property string $currency
 * @property int $items_count
 * @property float $subtotal_amount
 * @property float $total_amount
 * @property int $last_activity_at
 * @property int|null $expires_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property CustomerModel|null $customer
 * @property CartItemModel[] $items
 * @property OrderModel[] $orders
 */
class CartModel extends BaseModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONVERTED = 'converted';

    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_WIX = 'wix';

    public static function tableName(): string
    {
        return '{{%cart}}';
    }

    public static function find(): CartQuery
    {
        return new CartQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['hash', 'status', 'source_type', 'currency'], 'required'],
            [['customer_id', 'items_count', 'last_activity_at', 'expires_at', 'created_at', 'updated_at'], 'integer'],
            [['subtotal_amount', 'total_amount'], 'number'],
            [['hash'], 'string', 'max' => 64],
            [['session_key'], 'string', 'max' => 128],
            [['status', 'source_type'], 'string', 'max' => 32],
            [['currency'], 'string', 'max' => 3],

            [['hash'], 'unique'],

            [
                ['customer_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => CustomerModel::class,
                'targetAttribute' => ['customer_id' => 'id'],
            ],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'hash' => 'Хэш корзины',
            'customer_id' => 'Клиент',
            'session_key' => 'Session Key',
            'status' => 'Статус',
            'source_type' => 'Источник',
            'currency' => 'Валюта',
            'items_count' => 'Кол-во позиций',
            'subtotal_amount' => 'Subtotal',
            'total_amount' => 'Итого',
            'last_activity_at' => 'Последняя активность',
            'expires_at' => 'Истекает',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getCustomer()
    {
        return $this->hasOne(CustomerModel::class, ['id' => 'customer_id']);
    }

    public function getItems()
    {
        return $this->hasMany(CartItemModel::class, ['cart_id' => 'id']);
    }

    public function getOrders()
    {
        return $this->hasMany(OrderModel::class, ['cart_id' => 'id']);
    }

    public function getIsGuest(): bool
    {
        return empty($this->customer_id);
    }

    public function getIsActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}