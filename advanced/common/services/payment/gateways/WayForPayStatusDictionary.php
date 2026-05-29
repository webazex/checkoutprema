<?php

declare(strict_types=1);

namespace common\services\payment\gateways;

use common\models\payment\PaymentModel;

final class WayForPayStatusDictionary
{
    public const EXTERNAL_TO_INTERNAL_STATUS = [
        'Approved' => PaymentModel::STATUS_PAID,

        'Authorized' => PaymentModel::STATUS_AUTHORIZED,
        'WaitingAuthComplete' => PaymentModel::STATUS_AUTHORIZED,

        'Pending' => PaymentModel::STATUS_PENDING,
        'InProcessing' => PaymentModel::STATUS_PENDING,
        'inProcessing' => PaymentModel::STATUS_PENDING,
        'CreatedAwaiting3DS' => PaymentModel::STATUS_PENDING,
        'RefundInProcessing' => PaymentModel::STATUS_PENDING,

        'Refunded' => PaymentModel::STATUS_REFUNDED,

        'Voided' => PaymentModel::STATUS_CANCELLED,
        'Reversed' => PaymentModel::STATUS_CANCELLED,

        'Declined' => PaymentModel::STATUS_FAILED,
        'Expired' => PaymentModel::STATUS_FAILED,
        'Failed' => PaymentModel::STATUS_FAILED,
    ];

    public const EXTERNAL_TO_LABEL_KEY = [
        'Approved' => 'Paid',

        'Authorized' => 'Authorized',
        'WaitingAuthComplete' => 'Authorized',

        'Pending' => 'Processing',
        'InProcessing' => 'Processing',
        'inProcessing' => 'Processing',
        'CreatedAwaiting3DS' => 'Processing',
        'RefundInProcessing' => 'Processing',

        'Refunded' => 'Refunded',

        'Voided' => 'Cancelled',
        'Reversed' => 'Cancelled',

        'Declined' => 'Failed',
        'Expired' => 'Failed',
        'Failed' => 'Failed',
    ];

    public static function toInternalStatus(?string $externalStatus): string
    {
        $externalStatus = trim((string)$externalStatus);

        return self::EXTERNAL_TO_INTERNAL_STATUS[$externalStatus] ?? PaymentModel::STATUS_PENDING;
    }

    public static function toLabelKey(?string $externalStatus): string
    {
        $externalStatus = trim((string)$externalStatus);

        return self::EXTERNAL_TO_LABEL_KEY[$externalStatus] ?? 'Processing';
    }
}