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
    public ?string $branch_number = null;

    public ?string $payment_method = 'wayforpay';
    public ?string $returnUrl = null;

    public function rules(): array
    {
        return [
            [
                ['email', 'phone', 'first_name', 'last_name', 'branch_number'],
                'required',
                'message' => Yii::t('frontend', 'This field is required.'),
            ],

            [
                [
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
                    'branch_number',
                ],
                'trim',
            ],

            [
                'cartHash',
                function (string $attribute): void {
                    if (
                        trim((string)$this->cartHash) === ''
                        && trim((string)$this->sessionKey) === ''
                    ) {
                        $this->addError(
                            $attribute,
                            Yii::t('frontend', 'Cart hash or session key is required.')
                        );
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

            [
                'email',
                'email',
                'message' => Yii::t('frontend', 'Please enter a valid email address.'),
            ],

            ['phone', 'validatePhone'],

            [
                'branch_number',
                'match',
                'pattern' => '/^\d+$/',
                'message' => Yii::t('frontend', 'Nova Poshta branch number must contain only digits.'),
            ],

            [['cartHash', 'sessionKey'], 'string', 'max' => 128],
            [['sourceType'], 'string', 'max' => 32],
            [['email'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 30],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['region', 'city', 'branch'], 'string', 'max' => 255],
            [['branch_number'], 'string', 'max' => 20],
            [['payment_method'], 'string', 'max' => 64],
            [['payment_method'], 'in', 'range' => ['wayforpay']],
            [['returnUrl'], 'url'],
        ];
    }

    public function validatePhone(string $attribute): void
    {
        $raw = trim((string)$this->{$attribute});

        if ($raw === '') {
            $this->addError($attribute, Yii::t('frontend', 'This field is required.'));
            return;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === null || $digits === '') {
            $this->addError($attribute, Yii::t('frontend', 'Please enter a valid Ukrainian phone number.'));
            return;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '38' . $digits;
        }

        if (!preg_match('/^380\d{9}$/', $digits)) {
            $this->addError($attribute, Yii::t('frontend', 'Please enter a valid Ukrainian phone number.'));
            return;
        }

        $this->{$attribute} = '+' . $digits;
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
            'branch_number' => Yii::t('frontend', 'Specify Nova Poshta branch number'),
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
            'branch' => $this->branch ?: $this->branch_number,
            'branch_number' => $this->branch_number,
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