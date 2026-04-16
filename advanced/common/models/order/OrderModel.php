<?php

namespace common\models\order;
use common\models\BaseModel;
use common\models\cart\CartModel;
use common\models\customer\CustomerModel;
use common\models\payment\PaymentLogModel;
use common\models\payment\PaymentModel;

/**
 * @property int $id
 * @property string $hash
 * @property int $customer_id
 * @property int|null $cart_id
 * @property float $total_amount
 * @property string $currency
 * @property float $subtotal_amount
 * @property float $discount_amount
 * @property float $shipping_amount
 * @property string $status
 * @property string|null $payment_status
 * @property string|null $payment_method
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_first_name
 * @property string|null $customer_last_name
 * @property string $source_type
 * @property int|null $placed_at
 * @property int|null $paid_at
 * @property int|null $cancelled_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property CustomerModel $customer
 * @property CartModel|null $cart
 * @property OrderItemModel[] $items
 * @property PaymentModel[] $payments
 * @property PaymentLogModel[] $paymentLogs
 * @property string|null $keycrm_order_id
 */
class OrderModel extends BaseModel
{
    public const STATUS_NEW = 'new';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public static function tableName(): string
    {
        return '{{%order}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['hash', 'customer_id', 'total_amount', 'currency', 'subtotal_amount', 'discount_amount', 'shipping_amount', 'status', 'customer_email', 'source_type'], 'required'],
            [['customer_id', 'cart_id', 'placed_at', 'paid_at', 'cancelled_at', 'created_at', 'updated_at'], 'integer'],
            [['total_amount', 'subtotal_amount', 'discount_amount', 'shipping_amount'], 'number'],
            [['hash'], 'string', 'max' => 64],
            [['currency'], 'string', 'max' => 3],
            [['status', 'payment_status'], 'string', 'max' => 32],
            [['payment_method'], 'string', 'max' => 64],
            [['customer_email'], 'email'],
            [['customer_email'], 'string', 'max' => 255],
            [['customer_phone'], 'string', 'max' => 30],
            [['customer_first_name', 'customer_last_name'], 'string', 'max' => 100],
            [['source_type'], 'string', 'max' => 32],

            [['hash'], 'unique'],
            [['cart_id'], 'unique'],

            [
                ['customer_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => CustomerModel::class,
                'targetAttribute' => ['customer_id' => 'id'],
            ],
            [
                ['cart_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => CartModel::class,
                'targetAttribute' => ['cart_id' => 'id'],
            ],
            [['keycrm_order_id'], 'string', 'max' => 64],
            [['keycrm_order_id'], 'unique'],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'hash' => 'Хеш замовлення',
            'customer_id' => 'Клієнт',
            'cart_id' => 'Кошик',
            'total_amount' => 'Разом',
            'currency' => 'Валюта',
            'subtotal_amount' => 'Проміжний підсумок',
            'discount_amount' => 'Знижка',
            'shipping_amount' => 'Доставка',
            'status' => 'Статус замовлення',
            'payment_status' => 'Статус оплати',
            'payment_method' => 'Спосіб оплати',
            'customer_email' => 'E-mail клієнта',
            'customer_phone' => 'Телефон клієнта',
            'customer_first_name' => 'Ім’я клієнта',
            'customer_last_name' => 'Прізвище клієнта',
            'source_type' => 'Джерело',
            'placed_at' => 'Оформлено',
            'paid_at' => 'Оплачено',
            'cancelled_at' => 'Скасовано',
            'created_at' => 'Створено',
            'updated_at' => 'Оновлено',
            'keycrm_order_id' => 'KeyCRM Order ID',
        ];
    }

    public function getCustomer()
    {
        return $this->hasOne(CustomerModel::class, ['id' => 'customer_id']);
    }

    public function getCart()
    {
        return $this->hasOne(CartModel::class, ['id' => 'cart_id']);
    }

    public function getItems()
    {
        return $this->hasMany(OrderItemModel::class, ['order_id' => 'id']);
    }

    public function getPayments()
    {
        return $this->hasMany(PaymentModel::class, ['order_id' => 'id']);
    }

    public function getPaymentLogs()
    {
        return $this->hasMany(PaymentLogModel::class, ['order_id' => 'id']);
    }

    public function getCustomerFullName(): string
    {
        return trim((string)$this->customer_first_name . ' ' . (string)$this->customer_last_name);
    }
    public static function find(): OrderQuery
    {
        return new OrderQuery(static::class);
    }
}