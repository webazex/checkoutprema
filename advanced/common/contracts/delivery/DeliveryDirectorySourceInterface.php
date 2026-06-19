<?php

declare(strict_types=1);

namespace common\contracts\delivery;

use common\dto\delivery\DeliveryAreaSyncDto;
use common\dto\delivery\DeliveryPointSyncDto;
use common\dto\delivery\DeliverySettlementSyncDto;
use common\dto\delivery\DeliverySyncPageDto;

interface DeliveryDirectorySourceInterface
{
    /**
     * @return DeliverySyncPageDto<DeliveryAreaSyncDto>
     */
    public function fetchAreas(?string $cursor = null, ?int $limit = null): DeliverySyncPageDto;

    /**
     * @return DeliverySyncPageDto<DeliverySettlementSyncDto>
     */
    public function fetchSettlements(?string $areaExternalRef = null, ?string $cursor = null, ?int $limit = null): DeliverySyncPageDto;

    /**
     * @return DeliverySyncPageDto<DeliveryPointSyncDto>
     */
    public function fetchPoints(?string $settlementDeliveryRef = null, ?string $cursor = null, ?int $limit = null): DeliverySyncPageDto;
}