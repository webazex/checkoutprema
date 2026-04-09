<?php

declare(strict_types=1);

namespace common\models\checkout;

use yii\base\Model;

final class CheckoutSubmitInput extends Model
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
            ['email', 'filter', 'filter' => static fn ($v) => $v !== null ? mb_strtolower(trim((string)$v)) : null],
            ['email', 'email'],
            [['payment_method'], 'in', 'range' => ['wayforpay']],
            [['returnUrl'], 'url'],
            [['cartHash'], 'string', 'max' => 128],
            [['sessionKey'], 'string', 'max' => 128],
            [['sourceType'], 'string', 'max' => 32],
            [['email'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 30],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['region', 'city', 'branch'], 'string', 'max' => 255],
            [['cartHash'], 'validateCartTarget'],
        ];
    }

    public function validateCartTarget(string $attribute): void
    {
        $hasHash = trim((string)$this->cartHash) !== '';
        $hasSession = trim((string)$this->sessionKey) !== '';

        if (!$hasHash && !$hasSession) {
            $this->addError($attribute, 'Either cartHash or sessionKey must be provided.');
        }
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
}