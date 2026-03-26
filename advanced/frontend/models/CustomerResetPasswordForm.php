<?php

namespace frontend\models;

use Yii;
use yii\base\Model;
use common\models\customer\CustomerModel;
use common\models\customer\CustomerPasswordResetTokenModel;

class CustomerResetPasswordForm extends Model
{
    public ?string $password = null;
    public ?string $password_repeat = null;

    private CustomerPasswordResetTokenModel $_tokenModel;
    private CustomerModel $_customer;

    public function __construct(CustomerPasswordResetTokenModel $tokenModel, array $config = [])
    {
        $this->_tokenModel = $tokenModel;
        $this->_customer = $tokenModel->customer;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['password', 'password_repeat'], 'required'],
            [['password', 'password_repeat'], 'string', 'min' => 8, 'max' => 255],
            ['password_repeat', 'compare', 'compareAttribute' => 'password'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'password' => Yii::t('frontend', 'New password'),
            'password_repeat' => Yii::t('frontend', 'Repeat password'),
        ];
    }

    public function resetPassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if (!$this->_customer->isActive()) {
            $this->addError('password', Yii::t('frontend', 'Customer account is inactive.'));
            return false;
        }

        $this->_customer->setPassword((string)$this->password);
        $this->_customer->generateAuthKey();

        if ($this->_customer->hasAttribute('updated_at')) {
            $this->_customer->updated_at = time();
        }

        if (!$this->_customer->save(false, ['password_hash', 'auth_key', 'updated_at'])) {
            return false;
        }

        $this->_tokenModel->markAsUsed();

        return true;
    }

    public function getCustomer(): CustomerModel
    {
        return $this->_customer;
    }
}