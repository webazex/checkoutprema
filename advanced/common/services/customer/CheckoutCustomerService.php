<?php

namespace common\services\customer;

use RuntimeException;
use Yii;
use common\models\customer\CustomerModel;
use common\models\customer\CustomerPasswordResetTokenModel;

class CheckoutCustomerService
{
    public function process(array $data): CheckoutCustomerResult
    {
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $phone = trim((string)($data['phone'] ?? ''));
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));

        $result = new CheckoutCustomerResult();

        $customer = (new CustomerResolver())->resolveByCheckoutData($email);

        if ($customer !== null) {
            if ($customer->isBlocked() || $customer->isInactive()) {
                $result->status = CheckoutCustomerResult::STATUS_DENIED;
                $result->customer = $customer;
                $result->message = Yii::t(
                    'frontend',
                    'Your account has been deactivated. Please check your email or contact us.'
                );

                return $result;
            }

            $this->updateCustomerFromCheckout($customer, $phone, $firstName, $lastName);

            $result->status = CheckoutCustomerResult::STATUS_REUSED;
            $result->customer = $customer;
            $result->shouldLogin = Yii::$app->user->isGuest;

            return $result;
        }

        $customer = new CustomerModel();
        $customer->email = $email;
        $customer->phone = $phone !== '' ? $phone : null;
        $customer->first_name = $firstName !== '' ? $firstName : null;
        $customer->last_name = $lastName !== '' ? $lastName : null;
        $customer->status = CustomerModel::STATUS_ACTIVE;

        if (empty($customer->auth_key)) {
            $customer->generateAuthKey();
        }

        if (!$customer->save()) {
            Yii::error([
                'message' => 'Failed to create customer during checkout.',
                'errors' => $customer->errors,
                'email' => $email,
            ], __METHOD__);

            throw new RuntimeException('Failed to create customer during checkout.');
        }

        [, $rawToken] = CustomerPasswordResetTokenModel::createForCustomer($customer);

        $this->sendPasswordSetupEmail($customer, $rawToken);

        $result->status = CheckoutCustomerResult::STATUS_CREATED;
        $result->customer = $customer;
        $result->shouldLogin = true;
        $result->shouldSendPasswordSetupEmail = true;

        return $result;
    }

    protected function updateCustomerFromCheckout(
        CustomerModel $customer,
        string        $phone,
        string        $firstName,
        string        $lastName
    ): void
    {
        $changed = false;
        $fieldsToSave = [];

        if ($phone !== '' && $customer->phone !== $phone) {
            $customer->phone = $phone;
            $fieldsToSave[] = 'phone';
            $changed = true;
        }

        if ($firstName !== '' && $customer->first_name !== $firstName) {
            $customer->first_name = $firstName;
            $fieldsToSave[] = 'first_name';
            $changed = true;
        }

        if ($lastName !== '' && $customer->last_name !== $lastName) {
            $customer->last_name = $lastName;
            $fieldsToSave[] = 'last_name';
            $changed = true;
        }

        if ($changed) {
            if ($customer->hasAttribute('updated_at')) {
                $customer->updated_at = time();
                $fieldsToSave[] = 'updated_at';
            }

            $customer->save(false, $fieldsToSave);
        }
    }

    protected function sendPasswordSetupEmail(CustomerModel $customer, string $rawToken): void
    {
        $resetUrl = Yii::$app->urlManager->createAbsoluteUrl([
            '/customer/reset-password',
            'token' => $rawToken,
        ]);

        Yii::info([
            'message' => 'Customer password setup email prepared.',
            'customerId' => $customer->id,
            'email' => $customer->email,
            'resetUrl' => $resetUrl,
        ], __METHOD__);

        // Подключишь, когда mailer будет окончательно настроен:
        /*
        Yii::$app->mailer
            ->compose(
                ['html' => 'customer-password-reset-html', 'text' => 'customer-password-reset-text'],
                [
                    'customer' => $customer,
                    'resetUrl' => $resetUrl,
                ]
            )
            ->setTo($customer->email)
            ->setSubject(Yii::t('frontend', 'Set your password'))
            ->send();
        */
    }
}