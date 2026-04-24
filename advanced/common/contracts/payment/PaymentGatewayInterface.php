<?php

namespace common\contracts\payment;

use common\dto\payment\PaymentCallbackResponseDto;
use common\dto\payment\PaymentCallbackResultDto;
use common\dto\payment\PaymentCreateRequestDto;
use common\dto\payment\PaymentCreateResultDto;

interface PaymentGatewayInterface
{
    public function getCode(): string;

    public function createPayment(PaymentCreateRequestDto $request): PaymentCreateResultDto;

    public function validateCallback(array $payload): bool;

    public function parseCallback(array $payload): PaymentCallbackResultDto;

    public function buildCallbackResponse(PaymentCallbackResultDto $result): PaymentCallbackResponseDto;
}