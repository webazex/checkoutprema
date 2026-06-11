<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\models\product\ProductExternalMapModel;
use common\models\product\ProductModel;
use DomainException;
use Throwable;
use Yii;
use yii\helpers\Json;

final class KeyCrmStockWebhookService
{
    private const LOG_CATEGORY = 'keycrm.stock.webhook';

    /**
     * @param array<int, array<string, mixed>> $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array
    {
        $result = [
            'received' => count($payload),
            'updated' => 0,
            'unchanged' => 0,
            'notFound' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($payload as $index => $row) {
            if (!is_array($row)) {
                $result['failed']++;
                $result['details'][] = [
                    'index' => $index,
                    'status' => 'failed',
                    'reason' => 'Row must be an object.',
                ];

                Yii::warning(
                    KeyCrmSyncLogFormatter::event('stock-webhook', 'ROW_INVALID', [
                        'index' => $index,
                        'type' => get_debug_type($row),
                    ]),
                    self::LOG_CATEGORY
                );

                continue;
            }

            try {
                $normalized = $this->normalizeRow($row);

                $product = $this->resolveProduct(
                    offerId: $normalized['offerId'],
                    sku: $normalized['sku'],
                );

                if (!$product instanceof ProductModel) {
                    $result['notFound']++;
                    $result['details'][] = [
                        'index' => $index,
                        'status' => 'not_found',
                        'offerId' => $normalized['offerId'],
                        'sku' => $normalized['sku'],
                    ];

                    Yii::warning(
                        KeyCrmSyncLogFormatter::event('stock-webhook', 'PRODUCT_NOT_FOUND', [
                            'index' => $index,
                            'offerId' => $normalized['offerId'],
                            'sku' => $normalized['sku'],
                            'inStock' => $normalized['inStock'],
                            'inReserve' => $normalized['inReserve'],
                            'availableQuantity' => $normalized['availableQuantity'],
                        ]),
                        self::LOG_CATEGORY
                    );

                    continue;
                }

                $oldQuantity = (int)$product->quantity;
                $newQuantity = $normalized['availableQuantity'];

                if ($oldQuantity === $newQuantity) {
                    $result['unchanged']++;
                    $result['details'][] = [
                        'index' => $index,
                        'status' => 'unchanged',
                        'productId' => (int)$product->id,
                        'quantity' => $newQuantity,
                    ];

                    continue;
                }

                $product->quantity = $newQuantity;

                if (!$product->save(true, ['quantity', 'updated_at'])) {
                    throw new DomainException(
                        'Failed to save product quantity: ' .
                        Json::encode($product->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                    );
                }

                $result['updated']++;
                $result['details'][] = [
                    'index' => $index,
                    'status' => 'updated',
                    'productId' => (int)$product->id,
                    'oldQuantity' => $oldQuantity,
                    'newQuantity' => $newQuantity,
                    'offerId' => $normalized['offerId'],
                    'sku' => $normalized['sku'],
                ];

                Yii::info(
                    KeyCrmSyncLogFormatter::event('stock-webhook', 'UPDATED', [
                        'index' => $index,
                        'productId' => (int)$product->id,
                        'oldQuantity' => $oldQuantity,
                        'newQuantity' => $newQuantity,
                        'offerId' => $normalized['offerId'],
                        'sku' => $normalized['sku'],
                        'inStock' => $normalized['inStock'],
                        'inReserve' => $normalized['inReserve'],
                    ]),
                    self::LOG_CATEGORY
                );
            } catch (Throwable $e) {
                $result['failed']++;
                $result['details'][] = [
                    'index' => $index,
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ];

                Yii::error(
                    KeyCrmSyncLogFormatter::event('stock-webhook', 'ROW_FAILED', [
                        'index' => $index,
                        'exception' => $e::class,
                        'error' => $e->getMessage(),
                    ]),
                    self::LOG_CATEGORY
                );
            }
        }

        Yii::info(
            KeyCrmSyncLogFormatter::event('stock-webhook', 'DONE', [
                'received' => $result['received'],
                'updated' => $result['updated'],
                'unchanged' => $result['unchanged'],
                'notFound' => $result['notFound'],
                'failed' => $result['failed'],
            ]),
            self::LOG_CATEGORY
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *     offerId: ?string,
     *     sku: ?string,
     *     inStock: int,
     *     inReserve: int,
     *     availableQuantity: int
     * }
     */
    private function normalizeRow(array $row): array
    {
        $offerId = isset($row['offer_id']) && $row['offer_id'] !== ''
            ? (string)$row['offer_id']
            : null;

        $sku = isset($row['sku']) && trim((string)$row['sku']) !== ''
            ? trim((string)$row['sku'])
            : null;

        if ($offerId === null && $sku === null) {
            throw new DomainException('Either offer_id or sku is required.');
        }

        $inStock = max((int)($row['in_stock'] ?? 0), 0);
        $inReserve = max((int)($row['in_reserve'] ?? 0), 0);
        $availableQuantity = max($inStock - $inReserve, 0);

        return [
            'offerId' => $offerId,
            'sku' => $sku,
            'inStock' => $inStock,
            'inReserve' => $inReserve,
            'availableQuantity' => $availableQuantity,
        ];
    }

    private function resolveProduct(?string $offerId, ?string $sku): ?ProductModel
    {
        if ($offerId !== null) {
            $map = ProductExternalMapModel::find()
                ->bySourceAndExternalId(ProductExternalMapModel::SOURCE_KEYCRM, $offerId)
                ->with('product')
                ->one();

            if ($map instanceof ProductExternalMapModel && $map->product instanceof ProductModel) {
                return $map->product;
            }
        }

        if ($sku !== null) {
            $mapBySkuSnapshot = ProductExternalMapModel::find()
                ->source(ProductExternalMapModel::SOURCE_KEYCRM)
                ->bySkuSnapshot($sku)
                ->with('product')
                ->one();

            if ($mapBySkuSnapshot instanceof ProductExternalMapModel && $mapBySkuSnapshot->product instanceof ProductModel) {
                return $mapBySkuSnapshot->product;
            }

            $product = ProductModel::find()
                ->bySku($sku)
                ->one();

            if ($product instanceof ProductModel) {
                return $product;
            }
        }

        return null;
    }
}