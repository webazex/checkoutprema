<?php

declare(strict_types=1);

namespace common\mappers\catalog;

use common\dto\catalog\PublicProductDto;
use common\models\product\ProductModel;

final class PublicProductMapper
{
    public function mapOne(ProductModel $product): PublicProductDto
    {
        return new PublicProductDto(
            id: (int)$product->id,
            name: (string)$product->name,
            slug: (string)$product->slug,
            sku: $product->sku !== null ? (string)$product->sku : null,
            description: $product->description !== null ? (string)$product->description : null,
            price: (float)$product->price,
            currency: (string)$product->currency,
            quantity: (int)$product->quantity,
            isAvailable: $product->getIsAvailable(),
            thumbnailUrl: $product->thumbnail_url !== null ? (string)$product->thumbnail_url : null,
            categoryExternalId: $product->category_external_id !== null ? (string)$product->category_external_id : null,
        );
    }

    /**
     * @param ProductModel[] $products
     */
    public function mapMany(array $products): array
    {
        $result = [];

        foreach ($products as $product) {
            if (!$product instanceof ProductModel) {
                continue;
            }

            $result[] = $this->mapOne($product)->toArray();
        }

        return $result;
    }
}