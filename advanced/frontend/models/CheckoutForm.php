<?php

namespace frontend\models;

use Yii;
use yii\base\Model;

class CheckoutForm extends Model
{
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $first_name = null;
    public ?string $last_name = null;

    public ?string $region = null;
    public ?string $city = null;
    public ?string $branch = null;

    public ?string $payment_method = null;

    public function rules(): array
    {
        return [
            [['email', 'phone', 'first_name', 'last_name'], 'required'],
            [['region', 'city', 'branch', 'payment_method'], 'safe'],

            ['email', 'trim'],
            ['email', 'filter', 'filter' => static fn($value) => $value !== null ? mb_strtolower(trim((string)$value)) : null],
            ['email', 'email'],

            [['phone', 'first_name', 'last_name', 'region', 'city', 'branch', 'payment_method'], 'trim'],

            [['email'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 30],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['region', 'city', 'branch', 'payment_method'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => Yii::t('frontend', 'Email'),
            'phone' => Yii::t('frontend', 'Phone number'),
            'first_name' => Yii::t('frontend', 'First name'),
            'last_name' => Yii::t('frontend', 'Last name'),
            'region' => Yii::t('frontend', 'Select region'),
            'city' => Yii::t('frontend', 'Select city'),
            'branch' => Yii::t('frontend', 'Select branch'),
            'payment_method' => Yii::t('frontend', 'Payment'),
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
            'payment_method' => $this->payment_method,
        ];
    }
}