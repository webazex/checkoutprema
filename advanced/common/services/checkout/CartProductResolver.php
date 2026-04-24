<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\models\product\ProductExternalMapModel;
use common\models\product\ProductModel;
use DomainException;
use InvalidArgumentException;

final class CartProductResolver
{
    public function resolve(array $item): array
    {
        $quantity = (int)($item['quantity'] ?? 0);
        if ($quantity < 1) {
            throw new InvalidArgumentException('Field "quantity" must be >= 1.');
        }

        $productId = isset($item['productId']) ? (int)$item['productId'] : null;
        if ($productId !== null && $productId > 0) {
            $product = ProductModel::find()
                ->notArchived()
                ->byId($productId)
                ->one();

            if (!$product instanceof ProductModel) {
                throw new DomainException(sprintf(
                    'Product with id "%d" was not found.',
                    $productId
                ));
            }

            return [
                'product' => $product,
                'resolvedBy' => 'productId',
                'productId' => (int)$product->id,
                'externalSource' => null,
                'externalId' => null,
                'skuSnapshot' => $product->sku,
                'quantity' => $quantity,
            ];
        }

        $externalSource = strtolower(trim((string)($item['externalSource'] ?? '')));
        $externalId = trim((string)($item['externalId'] ?? ''));

        if ($externalSource === '' || $externalId === '') {
            throw new InvalidArgumentException(
                'Each item must contain either "productId", or both "externalSource" and "externalId".'
            );
        }

        $map = ProductExternalMapModel::find()
            ->bySourceAndExternalId($externalSource, $externalId)
            ->with('product')
            ->one();

        if ($map === null || !$map->product instanceof ProductModel) {
            throw new DomainException(sprintf(
                'Product mapping not found for source "%s" and external id "%s".',
                $externalSource,
                $externalId,
            ));
        }

        $product = $map->product;

        if ($product->getIsArchived()) {
            throw new DomainException(sprintf(
                'Mapped product for source "%s" and external id "%s" is archived.',
                $externalSource,
                $externalId,
            ));
        }

        return [
            'product' => $product,
            'resolvedBy' => 'externalMap',
            'productId' => (int)$product->id,
            'externalSource' => $externalSource,
            'externalId' => $externalId,
            'skuSnapshot' => $map->sku_snapshot ?: $product->sku,
            'quantity' => $quantity,
        ];
    }
}