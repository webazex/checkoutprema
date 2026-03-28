<?php

namespace common\models\payment;
use common\models\BaseModel;
use common\models\order\OrderModel;
/**
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property string|null $provider
 * @property string|null $event_type
 * @property string|null $direction
 * @property string|null $external_id
 * @property string $status
 * @property float $amount
 * @property string $currency
 * @property string|null $payment_method
 * @property string|null $response_data
 * @property string|null $error_message
 * @property int $created_at
 * @property int $updated_at
 *
 * @property OrderModel $order
 * @property PaymentModel|null $payment
 */
class PaymentLogModel extends BaseModel
{
    public const EVENT_REQUEST = 'request';
    public const EVENT_RESPONSE = 'response';
    public const EVENT_CALLBACK = 'callback';
    public const EVENT_WEBHOOK = 'webhook';
    public const EVENT_STATUS_CHANGE = 'status_change';
    public const EVENT_ERROR = 'error';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';
    public const DIRECTION_INTERNAL = 'internal';

    public static function tableName(): string
    {
        return '{{%payment_log}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['order_id', 'status', 'amount', 'currency'], 'required'],
            [['order_id', 'payment_id', 'created_at', 'updated_at'], 'integer'],
            [['amount'], 'number'],
            [['response_data', 'error_message'], 'string'],
            [['provider', 'event_type'], 'string', 'max' => 32],
            [['direction'], 'string', 'max' => 16],
            [['external_id'], 'string', 'max' => 100],
            [['status'], 'string', 'max' => 32],
            [['currency'], 'string', 'max' => 3],
            [['payment_method'], 'string', 'max' => 64],

            [
                ['order_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => OrderModel::class,
                'targetAttribute' => ['order_id' => 'id'],
            ],
            [
                ['payment_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => PaymentModel::class,
                'targetAttribute' => ['payment_id' => 'id'],
            ],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'order_id' => 'Заказ',
            'payment_id' => 'Payment',
            'provider' => 'Провайдер',
            'event_type' => 'Тип события',
            'direction' => 'Направление',
            'external_id' => 'Внешний ID',
            'status' => 'Статус',
            'amount' => 'Сумма',
            'currency' => 'Валюта',
            'payment_method' => 'Метод',
            'response_data' => 'Ответ провайдера',
            'error_message' => 'Ошибка',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(OrderModel::class, ['id' => 'order_id']);
    }

    public function getPayment()
    {
        return $this->hasOne(PaymentModel::class, ['id' => 'payment_id']);
    }

    public function getResponseDataDecoded(): array
    {
        if (empty($this->response_data)) {
            return [];
        }

        $decoded = json_decode($this->response_data, true);

        return is_array($decoded) ? $decoded : [];
    }
    public static function find(): PaymentLogQuery
    {
        return new PaymentLogQuery(static::class);
    }
}