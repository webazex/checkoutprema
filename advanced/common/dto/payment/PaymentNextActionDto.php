<?php

namespace common\dto\payment;

final class PaymentNextActionDto
{
    public const TYPE_NONE = 'none';
    public const TYPE_REDIRECT_GET = 'redirect_get';
    public const TYPE_REDIRECT_POST = 'redirect_post';
    public const TYPE_SDK_TOKEN = 'sdk_token';
    public const TYPE_WIDGET = 'widget';
    public const TYPE_QR = 'qr';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $url = null,
        public readonly string $method = 'GET',
        public readonly array $payload = [],
    ) {
    }

    public function isRedirect(): bool
    {
        return in_array($this->type, [
            self::TYPE_REDIRECT_GET,
            self::TYPE_REDIRECT_POST,
        ], true);
    }
}