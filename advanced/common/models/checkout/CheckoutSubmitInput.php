<?php

declare(strict_types=1);

namespace common\models\checkout;

use Yii;
use yii\base\Model;

class CheckoutSubmitInput extends Model
{
    public ?string $cartHash = null;
    public ?string $sessionKey = null;
    public ?string $sourceType = 'wix';

    public ?string $email = null;
    public ?string $phone = null;
    public ?string $first_name = null;
    public ?string $last_name = null;

    public ?string $region = null;
    public ?string $city = null;
    public ?string $branch = null;

    public ?string $payment_method = 'wayforpay';
    public ?string $returnUrl = null;

    public function rules(): array
    {
        return [
            [['email', 'phone', 'first_name', 'last_name'], 'required'],

            [['cartHash', 'sessionKey', 'sourceType', 'payment_method', 'returnUrl'], 'trim'],
            [['email', 'phone', 'first_name', 'last_name', 'region', 'city', 'branch'], 'trim'],

            [
                'cartHash',
                function (string $attribute): void {
                    if (
                        trim((string)$this->cartHash) === ''
                        && trim((string)$this->sessionKey) === ''
                    ) {
                        $this->addError($attribute, 'Cart hash or session key is required.');
                    }
                },
            ],

            [
                'email',
                'filter',
                'filter' => static fn($value) => $value !== null
                    ? mb_strtolower(trim((string)$value))
                    : null,
            ],
            ['email', 'email'],

            [['cartHash', 'sessionKey'], 'string', 'max' => 128],
            [['sourceType'], 'string', 'max' => 32],
            [['email'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 30],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['region', 'city', 'branch'], 'string', 'max' => 255],

            [['payment_method'], 'string', 'max' => 64],
            [['payment_method'], 'in', 'range' => ['wayforpay']],

            [['returnUrl'], 'url'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'cartHash' => 'Cart Hash',
            'sessionKey' => 'Session Key',
            'sourceType' => 'Source Type',
            'email' => Yii::t('frontend', 'Email'),
            'phone' => Yii::t('frontend', 'Phone number'),
            'first_name' => Yii::t('frontend', 'First name'),
            'last_name' => Yii::t('frontend', 'Last name'),
            'region' => Yii::t('frontend', 'Select region'),
            'city' => Yii::t('frontend', 'Select city'),
            'branch' => Yii::t('frontend', 'Select branch'),
            'payment_method' => Yii::t('frontend', 'Payment'),
            'returnUrl' => 'Return URL',
        ];
    }

    public function getCustomerPayload(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }

    public function getDeliveryPayload(): array
    {
        return [
            'region' => $this->region,
            'city' => $this->city,
            'branch' => $this->branch,
        ];
    }

    public function getPaymentPayload(): array
    {
        return [
            'payment_method' => $this->payment_method ?: 'wayforpay',
            'returnUrl' => $this->returnUrl,
        ];
    }
}