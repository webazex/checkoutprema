<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\models\customer\CustomerModel;
use DomainException;

final class CheckoutCustomerResolver
{
    public function resolve(array $payload): CustomerModel
    {
        $email = mb_strtolower(trim((string)($payload['email'] ?? '')));
        if ($email === '') {
            throw new DomainException('Customer email is required.');
        }

        $customer = CustomerModel::find()
            ->byEmail($email)
            ->one();

        if (!$customer instanceof CustomerModel) {
            $customer = new CustomerModel();
            $customer->email = $email;
            $customer->status = CustomerModel::STATUS_ACTIVE;
        }

        $customer->phone = $this->nullableString($payload['phone'] ?? null);
        $customer->first_name = $this->nullableString($payload['first_name'] ?? null);
        $customer->last_name = $this->nullableString($payload['last_name'] ?? null);

        if (!$customer->save()) {
            throw new DomainException(
                'Failed to save customer: ' . json_encode($customer->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $customer;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;
        return $value !== '' ? $value : null;
    }
}