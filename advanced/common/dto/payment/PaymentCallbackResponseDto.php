<?php

namespace common\dto\payment;

final class PaymentCallbackResponseDto
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ],
    ) {
    }
}