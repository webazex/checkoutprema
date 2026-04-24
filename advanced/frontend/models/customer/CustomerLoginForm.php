<?php

namespace frontend\models;

use Yii;
use yii\base\Model;
use common\models\customer\CustomerModel;

class CustomerLoginForm extends Model
{
    public ?string $email = null;
    public ?string $password = null;
    public bool $rememberMe = true;

    private ?CustomerModel $_customer = null;

    public function rules(): array
    {
        return [
            [['email', 'password'], 'required'],
            ['email', 'trim'],
            ['email', 'filter', 'filter' => static fn($value) => $value !== null ? mb_strtolower((string)$value) : null],
            ['email', 'email'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => Yii::t('frontend', 'Email'),
            'password' => Yii::t('frontend', 'Password'),
            'rememberMe' => Yii::t('frontend', 'Remember me'),
        ];
    }

    public function validatePassword(string $attribute, mixed $params): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $customer = $this->getCustomer();

        if (
            !$customer
            || !$customer->isActive()
            || !$customer->validatePassword((string)$this->password)
        ) {
            $this->addError(
                $attribute,
                Yii::t('frontend', 'Incorrect email or password.')
            );
        }
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $customer = $this->getCustomer();
        if ($customer === null || !$customer->isActive()) {
            return false;
        }

        $duration = $this->rememberMe ? 3600 * 24 * 30 : 0;

        return Yii::$app->user->login($customer, $duration);
    }

    public function getCustomer(): ?CustomerModel
    {
        if ($this->_customer === null) {
            $this->_customer = CustomerModel::findByEmail((string)$this->email);
        }

        return $this->_customer;
    }
}