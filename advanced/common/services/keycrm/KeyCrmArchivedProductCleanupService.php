<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\models\product\ProductModel;
use DomainException;
use yii\helpers\Json;

final class KeyCrmArchivedProductCleanupService
{
    private const ARCHIVE_TTL_DAYS = 7;

    public function cleanup(?int $olderThanDays = null): array
    {
        $days = $olderThanDays !== null && $olderThanDays > 0
            ? $olderThanDays
            : self::ARCHIVE_TTL_DAYS;

        $threshold = time() - ($days * 24 * 60 * 60);

        $stats = [
            'scanned' => 0,
            'eligible' => 0,
            'deleted' => 0,
            'skippedCartItems' => 0,
            'skippedOrderItems' => 0,
            'skippedMissingArchivedAt' => 0,
            'errors' => 0,
        ];

        /** @var ProductModel[] $products */
        $products = ProductModel::find()
            ->where(['is_archived' => 1])
            ->andWhere(['not', ['archived_at' => null]])
            ->andWhere(['<=', 'archived_at', $threshold])
            ->with(['externalMaps', 'cartItems', 'orderItems'])
            ->all();

        $stats['scanned'] = count($products);

        foreach ($products as $product) {
            try {
                if (empty($product->archived_at)) {
                    $stats['skippedMissingArchivedAt']++;
                    continue;
                }

                $stats['eligible']++;

                if (!empty($product->cartItems)) {
                    $stats['skippedCartItems']++;
                    continue;
                }

                if (!empty($product->orderItems)) {
                    $stats['skippedOrderItems']++;
                    continue;
                }

                foreach ($product->externalMaps as $externalMap) {
                    if ($externalMap->delete() === false) {
                        throw new DomainException(
                            'Failed to delete product external map: ' . Json::encode($externalMap->errors)
                        );
                    }
                }

                if ($product->delete() === false) {
                    throw new DomainException(
                        'Failed to delete archived product #' . $product->id
                    );
                }

                $stats['deleted']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                throw $e;
            }
        }

        return $stats;
    }
}