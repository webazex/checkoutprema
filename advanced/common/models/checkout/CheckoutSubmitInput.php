<?php

declare(strict_types=1);

namespace common\models\checkout;

use Yii;
use yii\base\Model;

class CheckoutSubmitInput extends Model
{
    private const DEFAULT_SOURCE_TYPE = 'wix';
    private const DEFAULT_PAYMENT_METHOD = 'wayforpay';

    private const PAYMENT_METHODS = [
        self::DEFAULT_PAYMENT_METHOD,
    ];

    private const REQUIRED_FIELDS = [
        'email',
        'phone',
        'first_name',
        'last_name',
        'branch',
    ];

    private const TRIM_FIELDS = [
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
    ];

    public ?string $cartHash = null;
    public ?string $sessionKey = null;
    public ?string $sourceType = self::DEFAULT_SOURCE_TYPE;

    public ?string $email = null;
    public ?string $phone = null;
    public ?string $first_name = null;
    public ?string $last_name = null;

    public ?string $region = null;
    public ?string $city = null;

    /**
     * Основное поле отделения доставки.
     *
     * Сейчас во view в это поле вводится номер отделения Новой пошты.
     */
    public ?string $branch = null;

    /**
     * Совместимый alias для внутренних сервисов / старого payload.
     *
     * Не является отдельным полем формы.
     */
    public ?string $branch_number = null;

    public ?string $payment_method = self::DEFAULT_PAYMENT_METHOD;
    public ?string $returnUrl = null;

    public function rules(): array
    {
        return [
            [
                self::REQUIRED_FIELDS,
                'required',
                'message' => Yii::t('frontend', 'This field is required.'),
            ],

            [
                'cartHash',
                'validateCartIdentifier',
            ],

            [
                'email',
                'email',
                'message' => Yii::t('frontend', 'Please enter a valid email address.'),
            ],

            [
                'phone',
                'validatePhone',
            ],

            [
                'branch',
                'match',
                'pattern' => '/^\d+$/',
                'message' => Yii::t('frontend', 'Nova Poshta branch number must contain only digits.'),
            ],

            [['cartHash', 'sessionKey'], 'string', 'max' => 128],
            [['sourceType'], 'string', 'max' => 32],
            [['email'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 30],
            [['first_name', 'last_name'], 'string', 'max' => 100],
            [['region', 'city'], 'string', 'max' => 255],
            [['branch', 'branch_number'], 'string', 'max' => 20],

            [
                'payment_method',
                'string',
                'max' => 64,
            ],

            [
                'payment_method',
                'in',
                'range' => self::PAYMENT_METHODS,
            ],

            [
                'returnUrl',
                'url',
            ],
        ];
    }

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $this->normalizeStringFields();
        $this->normalizeEmail();
        $this->normalizeBranchAlias();

        return true;
    }

    public function validateCartIdentifier(string $attribute): void
    {
        if ($this->isFilled($this->cartHash) || $this->isFilled($this->sessionKey)) {
            return;
        }

        $this->addError(
            $attribute,
            Yii::t('frontend', 'Cart hash or session key is required.')
        );
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
            $this->addError(
                $attribute,
                Yii::t('frontend', 'Please enter a valid Ukrainian phone number.')
            );
            return;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '38' . $digits;
        }

        if (!preg_match('/^380\d{9}$/', $digits)) {
            $this->addError(
                $attribute,
                Yii::t('frontend', 'Please enter a valid Ukrainian phone number.')
            );
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
            'branch' => Yii::t('frontend', 'Specify Nova Poshta branch number'),
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

            // Основное значение для заказа.
            'branch' => $this->branch,

            // Alias на тот же номер отделения, чтобы не сломать существующий код,
            // если где-то дальше уже читается branch_number.
            'branch_number' => $this->branch_number,
        ];
    }

    public function getPaymentPayload(): array
    {
        return [
            'payment_method' => $this->payment_method ?: self::DEFAULT_PAYMENT_METHOD,
            'returnUrl' => $this->returnUrl,
        ];
    }

    private function normalizeStringFields(): void
    {
        foreach (self::TRIM_FIELDS as $attribute) {
            if ($this->{$attribute} === null) {
                continue;
            }

            $value = trim((string)$this->{$attribute});
            $this->{$attribute} = $value === '' ? null : $value;
        }
    }

    private function normalizeEmail(): void
    {
        if ($this->email === null) {
            return;
        }

        $this->email = mb_strtolower($this->email);
    }

    private function normalizeBranchAlias(): void
    {
        if (!$this->isFilled($this->branch) && $this->isFilled($this->branch_number)) {
            $this->branch = $this->branch_number;
        }

        if (!$this->isFilled($this->branch_number) && $this->isFilled($this->branch)) {
            $this->branch_number = $this->branch;
        }
    }

    private function isFilled(?string $value): bool
    {
        return trim((string)$value) !== '';
    }
}