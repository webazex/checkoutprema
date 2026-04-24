<?php

namespace common\dto;

/**
 * Нормализованный DTO входящих данных storefront / Wix.
 *
 * ВАЖНО:
 * Структура Wix пока не финализирована, поэтому DTO максимально мягкий.
 */
class WixCartPayloadDto
{
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $externalVisitorId = null;
    public ?string $customerHash = null;

    /**
     * Нормализованные товары вида:
     * [
     *   ['productId' => 123, 'qty' => 2],
     *   ...
     * ]
     */
    public array $items = [];

    /**
     * Сырые входящие данные для отладки.
     */
    public array $rawPayload = [];

    public function hasItems(): bool
    {
        return !empty($this->items);
    }

    public function hasIdentityHints(): bool
    {
        return !empty($this->email)
            || !empty($this->phone)
            || !empty($this->externalVisitorId)
            || !empty($this->customerHash);
    }
}