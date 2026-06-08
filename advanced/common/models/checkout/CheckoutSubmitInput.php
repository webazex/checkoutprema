<?php

declare(strict_types=1);

namespace common\models\checkout;

use Yii;
use yii\base\Model;

class CheckoutSubmitInput extends Model
{
    private const SOURCE_TYPE_WIX = 'wix';
    private const PAYMENT_METHOD_WAYFORPAY = 'wayforpay';

    private const PHONE_MAX_LENGTH = 30;
    private const NAME_MAX_LENGTH = 100;
    private const EMAIL_MAX_LENGTH = 255;
    private const DELIVERY_FIELD_MAX_LENGTH = 255;
    private const BRANCH_MAX_LENGTH = 20;
    private const CART_REFERENCE_MAX_LENGTH = 128;
    private const SOURCE_TYPE_MAX_LENGTH = 32;
    private const PAYMENT_METHOD_MAX_LENGTH = 64;

    public ?string $cartHash = null;
    public ?string $sessionKey = null;
    public ?string $sourceType = self::SOURCE_TYPE_WIX;

    public ?string $email = null;
    public ?string $phone = null;
    public ?string $first_name = null;
    public ?string $last_name = null;

    public ?string $region = null;
    public ?string $city = null;

    /**
     * Номер відділення Нової пошти.
     *
     * Сохраняется в заказ как delivery.branch.
     */
    public ?string $branch = null;

    public ?string $payment_method = self::PAYMENT_METHOD_WAYFORPAY;
    public ?string $returnUrl = null;

    public function rules(): array
    {
        return [
            [
                $this->requiredAttributes(),
                'required',
                'message' => Yii::t('frontend', 'This field is required.'),
            ],

            [
                $this->trimmedAttributes(),
                'trim',
            ],

            [
                'cartHash',
                'validateCartReference',
                'skipOnEmpty' => false,
            ],

            [
                'email',
                'filter',
                'filter' => static fn($value): ?string => $value !== null
                    ? mb_strtolower(trim((string)$value))
                    : null,
            ],

            [
                'email',
                'email',
                'message' => Yii::t('frontend', 'Please enter a valid email address.'),
            ],

            [
                'phone',
                'validatePhone',
                'skipOnEmpty' => false,
            ],

            [
                'branch',
                'match',
                'pattern' => '/^\d+$/',
                'message' => Yii::t('frontend', 'Nova Poshta branch number must contain only digits.'),
            ],

            [['cartHash', 'sessionKey'], 'string', 'max' => self::CART_REFERENCE_MAX_LENGTH],
            ['sourceType', 'string', 'max' => self::SOURCE_TYPE_MAX_LENGTH],
            ['email', 'string', 'max' => self::EMAIL_MAX_LENGTH],
            ['phone', 'string', 'max' => self::PHONE_MAX_LENGTH],
            [['first_name', 'last_name'], 'string', 'max' => self::NAME_MAX_LENGTH],
            [['region', 'city'], 'string', 'max' => self::DELIVERY_FIELD_MAX_LENGTH],
            ['branch', 'string', 'max' => self::BRANCH_MAX_LENGTH],

            ['payment_method', 'string', 'max' => self::PAYMENT_METHOD_MAX_LENGTH],
            ['payment_method', 'in', 'range' => [self::PAYMENT_METHOD_WAYFORPAY]],

            ['returnUrl', 'url'],
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
            'branch' => Yii::t('frontend', 'Specify Nova Poshta branch number'),

            'payment_method' => Yii::t('frontend', 'Payment'),
            'returnUrl' => 'Return URL',
        ];
    }

    public function validateCartReference(string $attribute): void
    {
        if ($this->hasCartReference()) {
            return;
        }

        $this->addError(
            $attribute,
            Yii::t('frontend', 'Cart hash or session key is required.')
        );
    }

    public function validatePhone(string $attribute): void
    {
        $rawPhone = trim((string)$this->{$attribute});

        if ($rawPhone === '') {
            $this->addError($attribute, Yii::t('frontend', 'This field is required.'));
            return;
        }

        $normalizedPhone = $this->normalizePhone($rawPhone);

        if ($normalizedPhone === null) {
            $this->addError($attribute, Yii::t('frontend', 'Please enter a valid Ukrainian phone number.'));
            return;
        }

        $this->{$attribute} = $normalizedPhone;
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
            'payment_method' => $this->payment_method ?: self::PAYMENT_METHOD_WAYFORPAY,
            'returnUrl' => $this->returnUrl,
        ];
    }

    private function requiredAttributes(): array
    {
        return [
            'email',
            'phone',
            'first_name',
            'last_name',
            'branch',
        ];
    }

    private function trimmedAttributes(): array
    {
        return [
            'cartHash',
            'sessionKey',
            'sourceType',
            'payment_method',
            'returnUrl',

            'email',
            'phone',
            'first_name',
            'last_name',

            'region',
            'city',
            'branch',
        ];
    }

    private function hasCartReference(): bool
    {
        return trim((string)$this->cartHash) !== ''
            || trim((string)$this->sessionKey) !== '';
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '38' . $digits;
        }

        if (!preg_match('/^380\d{9}$/', $digits)) {
            return null;
        }

        return '+' . $digits;
    }
}