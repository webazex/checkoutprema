<?php

namespace common\contracts\payment;

use common\dto\payment\PaymentCreateRequestDto;
use common\dto\payment\PaymentCreateResultDto;
use common\dto\payment\PaymentCallbackResultDto;

interface PaymentGatewayInterface
{
    public function getCode(): string;

    public function createPayment(PaymentCreateRequestDto $request): PaymentCreateResultDto;

    public function parseCallback(array $payload): PaymentCallbackResultDto;

    public function validateCallback(array $payload): bool;
}