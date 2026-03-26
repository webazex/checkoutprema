<?php

namespace frontend\dto;

/**
 * DTO результата входного сценария.
 */
class CartEntryResultDto
{
    public string $customerHash;
    public int $orderId;

    public bool $isKnownCustomer = false;
    public bool $usedIncomingItems = false;
    public bool $usedAbandonedCart = false;
    public bool $createdNewDraft = false;
}