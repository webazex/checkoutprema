<?php

namespace common\services\customer;

use common\models\customer\CustomerModel;

class CheckoutCustomerResult
{
    public const STATUS_CREATED = 'created';
    public const STATUS_REUSED = 'reused';
    public const STATUS_DENIED = 'denied';

    public ?CustomerModel $customer = null;
    public ?string $status = null;
    public ?string $message = null;

    public bool $shouldLogin = false;
    public bool $shouldSendPasswordSetupEmail = false;
}