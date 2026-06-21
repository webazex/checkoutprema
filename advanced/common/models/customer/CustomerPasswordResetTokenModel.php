<?php

namespace common\models\customer;

use common\models\BaseModel;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $token_hash
 * @property int $expires_at
 * @property int|null $used_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property CustomerModel $customer
 */
class CustomerPasswordResetTokenModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%customer_password_reset_token}}';
    }

    public static function createForCustomer(CustomerModel $customer)
    {
        return "test-token-str";
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['customer_id', 'token_hash', 'expires_at'], 'required'],
            [['customer_id', 'expires_at', 'used_at', 'created_at', 'updated_at'], 'integer'],
            [['token_hash'], 'string', 'max' => 255],
            [['token_hash'], 'unique'],
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
            'customer_id' => 'Клиент',
            'token_hash' => 'Хэш токена',
            'expires_at' => 'Истекает',
            'used_at' => 'Использован',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getCustomer()
    {
        return $this->hasOne(CustomerModel::class, ['id' => 'customer_id']);
    }

    public function getIsExpired(): bool
    {
        return $this->expires_at < time();
    }

    public function getIsUsed(): bool
    {
        return !empty($this->used_at);
    }
}