<?php

namespace common\services\customer;

use common\dto\WixCartPayloadDto;
use common\models\customer\CustomerModel;

class CustomerResolver
{
    public function resolveByPayload(WixCartPayloadDto $payload): ?CustomerModel
    {
        if (!empty($payload->customerHash)) {
            $customer = CustomerModel::findByHash($payload->customerHash, true);
            if ($customer !== null) {
                return $customer;
            }
        }

        if (!empty($payload->email)) {
            $customer = CustomerModel::findByEmail($payload->email, true);
            if ($customer !== null) {
                return $customer;
            }
        }

        return null;
    }

    public function resolveByCheckoutData(string $email): ?CustomerModel
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        return CustomerModel::findByEmail($email, false);
    }
}