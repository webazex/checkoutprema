<?php

declare(strict_types=1);

namespace common\dto\delivery;

use common\enums\delivery\DeliverySyncScope;
use common\enums\delivery\DeliverySyncStatus;

final readonly class DeliverySyncStateReadDto
{
    public function __construct(
        public int                $id,
        public int                $providerId,
        public string             $providerCode,
        public DeliverySyncScope  $scope,
        public string             $scopeExternalRef,
        public DeliverySyncStatus $status,
        public ?string            $runToken,
        public ?string            $cursor,
        public ?int               $startedAt,
        public ?int               $heartbeatAt,
        public ?int               $finishedAt,
        public ?int               $lastSuccessAt,
        public ?int               $lastErrorAt,
        public ?string            $lastErrorType,
        public ?string            $lastErrorCode,
        public ?string            $lastErrorMessage,
        public ?int               $sourceTotalCount,
        public int                $processedCount,
        public int                $createdCount,
        public int                $updatedCount,
        public int                $archivedCount,
    )
    {
        DeliveryDtoAssertion::positiveInt($id, 'id');
        DeliveryDtoAssertion::positiveInt($providerId, 'providerId');
        DeliveryDtoAssertion::providerCode($providerCode);

        DeliveryDtoAssertion::optionalString($runToken, 'runToken');
        DeliveryDtoAssertion::optionalString($lastErrorType, 'lastErrorType');
        DeliveryDtoAssertion::optionalString($lastErrorCode, 'lastErrorCode');
        DeliveryDtoAssertion::optionalString(
            $lastErrorMessage,
            'lastErrorMessage'
        );

        foreach ([
                     'processedCount' => $processedCount,
                     'createdCount' => $createdCount,
                     'updatedCount' => $updatedCount,
                     'archivedCount' => $archivedCount,
                 ] as $field => $value) {
            DeliveryDtoAssertion::nonNegativeInt($value, $field);
        }

        if ($sourceTotalCount !== null) {
            DeliveryDtoAssertion::nonNegativeInt(
                $sourceTotalCount,
                'sourceTotalCount'
            );
        }
    }
}