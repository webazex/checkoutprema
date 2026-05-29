<?php

namespace common\models\payment;

use common\models\BaseModel;
use common\models\customer\CustomerModel;
use common\models\order\OrderModel;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $customer_id
 * @property string $provider
 * @property string $status
 * @property float $amount
 * @property string $currency
 * @property string|null $payment_method
 * @property string|null $external_id
 * @property string|null $external_order_id
 * @property string|null $idempotency_key
 * @property string|null $redirect_url
 * @property string|null $error_message
 * @property int|null $paid_at
 * @property int|null $failed_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property OrderModel $order
 * @property CustomerModel|null $customer
 * @property PaymentLogModel[] $logs
 */
class PaymentModel extends BaseModel
{
    public const STATUS_NEW = 'new';
    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const PROVIDER_WAYFORPAY = 'wayforpay';
    public const PROVIDER_LEGACY = 'legacy';

    public const PROVIDERS = [
        self::PROVIDER_WAYFORPAY,
        self::PROVIDER_LEGACY,
    ];



    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PENDING,
        self::STATUS_AUTHORIZED,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
    ];

    public const FINAL_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
    ];

    public const SUCCESS_STATUSES = [
        self::STATUS_PAID,
    ];

    public const FAILURE_STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABEL_KEYS = [
        self::STATUS_PENDING => 'Processing',
        self::STATUS_AUTHORIZED => 'Authorized',
        self::STATUS_PAID => 'Paid',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_REFUNDED => 'Refunded',
    ];

    public static function getStatusLabelKey(?string $status): string
    {
        $status = trim((string)$status);

        return self::STATUS_LABEL_KEYS[$status] ?? 'Processing';
    }

    public static function tableName(): string
    {
        return '{{%payment}}';
    }

    public static function find(): PaymentQuery
    {
        return new PaymentQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['order_id', 'provider', 'status', 'amount', 'currency'], 'required'],
            [['order_id', 'customer_id', 'paid_at', 'failed_at', 'created_at', 'updated_at'], 'integer'],
            [['amount'], 'number'],
            [['error_message'], 'string'],
            [['provider', 'status'], 'string', 'max' => 32],
            [['currency'], 'string', 'max' => 3],
            [['payment_method'], 'string', 'max' => 64],
            [['external_id', 'external_order_id', 'idempotency_key'], 'string', 'max' => 100],
            [['redirect_url'], 'string', 'max' => 1024],

            [['provider'], 'string', 'max' => 32],
            [['status'], 'in', 'range' => self::STATUSES],

            [['idempotency_key'], 'unique'],

            [
                ['order_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => OrderModel::class,
                'targetAttribute' => ['order_id' => 'id'],
            ],
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
            'order_id' => 'Заказ',
            'customer_id' => 'Клиент',
            'provider' => 'Провайдер',
            'status' => 'Статус',
            'amount' => 'Сумма',
            'currency' => 'Валюта',
            'payment_method' => 'Метод',
            'external_id' => 'Внешний ID',
            'external_order_id' => 'Внешний ID заказа',
            'idempotency_key' => 'Idempotency Key',
            'redirect_url' => 'URL редиректа',
            'error_message' => 'Ошибка',
            'paid_at' => 'Оплачен',
            'failed_at' => 'Ошибка оплаты',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(OrderModel::class, ['id' => 'order_id']);
    }

    public function getCustomer()
    {
        return $this->hasOne(CustomerModel::class, ['id' => 'customer_id']);
    }

    public function getLogs()
    {
        return $this->hasMany(PaymentLogModel::class, ['payment_id' => 'id']);
    }

    public function getIsSuccessful(): bool
    {
        return in_array($this->status, self::SUCCESS_STATUSES, true);
    }

    public function getIsFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function getIsPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getIsAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    public function getIsFailed(): bool
    {
        return in_array($this->status, self::FAILURE_STATUSES, true);
    }

    public function getIsRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }
}