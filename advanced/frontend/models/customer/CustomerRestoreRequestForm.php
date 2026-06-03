<?php

namespace frontend\models\customer;

use Yii;
use yii\base\Model;
use common\models\customer\CustomerModel;
use common\models\customer\CustomerPasswordResetTokenModel;

class CustomerRestoreRequestForm extends Model
{
    public ?string $email = null;

    private ?CustomerModel $_customer = null;

    public function rules(): array
    {
        return [
            [['email'], 'required'],
            ['email', 'trim'],
            ['email', 'filter', 'filter' => static fn($value) => $value !== null ? mb_strtolower((string)$value) : null],
            ['email', 'email'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'email' => Yii::t('frontend', 'Email'),
        ];
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $customer = $this->getCustomer();

        if ($customer === null || !$customer->isActive()) {
            return true;
        }

        try {
            [, $rawToken] = CustomerPasswordResetTokenModel::createForCustomer($customer);
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'Failed to create customer password reset token.',
                'email' => $this->email,
                'exception' => $e->getMessage(),
            ], __METHOD__);

            return true;
        }

        return $this->sendRestoreEmail($customer, $rawToken);
    }

    public function getCustomer(): ?CustomerModel
    {
        if ($this->_customer === null) {
            $this->_customer = CustomerModel::findByEmail((string)$this->email);
        }

        return $this->_customer;
    }

    protected function sendRestoreEmail(CustomerModel $customer, string $rawToken): bool
    {
        $resetUrl = Yii::$app->urlManager->createAbsoluteUrl([
            '/customer/reset-password',
            'token' => $rawToken,
        ]);

        try {
            return Yii::$app->mailer
                ->compose(
                    ['html' => 'customer-password-reset-html', 'text' => 'customer-password-reset-text'],
                    [
                        'customer' => $customer,
                        'resetUrl' => $resetUrl,
                    ]
                )
                ->setTo($customer->email)
                ->setSubject(Yii::t('frontend', 'Password reset'))
                ->send();
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'Failed to send customer password reset email.',
                'customerId' => $customer->id,
                'email' => $customer->email,
                'exception' => $e->getMessage(),
            ], __METHOD__);

            return true;
        }
    }
}