<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\integrations\keycrm\KeyCrmApiClient;
use common\integrations\keycrm\dto\KeyCrmProductDto;
use common\integrations\keycrm\mappers\KeyCrmProductMapper;
use common\models\product\ProductExternalMapModel;
use common\models\product\ProductModel;
use DomainException;
use RuntimeException;
use Yii;
use yii\db\Connection;
use yii\helpers\Inflector;
use yii\helpers\Json;

final class KeyCrmProductImportService
{
    public function __construct(
        private readonly KeyCrmApiClient $apiClient,
        private readonly KeyCrmProductMapper $productMapper,
    ) {
    }

    public function importAllProducts(bool $withCustomFields = true, ?int $maxPages = null): array
    {
        $page = 1;
        $stats = [
            'pages' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'mapped' => 0,
        ];

        do {
            if ($maxPages !== null && $maxPages > 0 && $page > $maxPages) {
                break;
            }

            $response = $this->apiClient->get('/products', [
                'include' => $withCustomFields ? 'custom_fields' : null,
                'page' => $page,
            ]);

            $items = is_array($response['data'] ?? null) ? $response['data'] : [];
            $products = $this->productMapper->mapMany($items);

            foreach ($products as $dto) {
                $result = $this->upsertProduct($dto);
                $stats['processed']++;

                if ($result['created']) {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }

                if ($result['mappingCreated']) {
                    $stats['mapped']++;
                }
            }

            $stats['pages']++;
            $page++;

            $hasNext = !empty($response['next_page_url']);
        } while ($hasNext);

        return $stats;
    }

    private function upsertProduct(KeyCrmProductDto $dto): array
    {
        /** @var Connection $db */
        $db = Yii::$app->db;

        return $db->transaction(function () use ($dto): array {
            $map = ProductExternalMapModel::find()
                ->bySourceAndExternalId(ProductExternalMapModel::SOURCE_KEYCRM, (string)$dto->externalId)
                ->with('product')
                ->one();

            $created = false;
            $mappingCreated = false;

            if ($map instanceof ProductExternalMapModel && $map->product instanceof ProductModel) {
                $product = $map->product;
            } else {
                $product = $this->findExistingProductForLinking($dto);

                if (!$product instanceof ProductModel) {
                    $product = new ProductModel();
                    $created = true;
                }
            }

            $this->fillProduct($product, $dto);

            if (!$product->save()) {
                throw new DomainException('Failed to save product: ' . Json::encode($product->getFirstErrors(), JSON_UNESCAPED_UNICODE));
            }

            if (!$map instanceof ProductExternalMapModel) {
                $map = new ProductExternalMapModel();
                $map->product_id = (int)$product->id;
                $map->external_source = ProductExternalMapModel::SOURCE_KEYCRM;
                $map->external_id = (string)$dto->externalId;
                $map->sku_snapshot = $dto->sku;
                $mappingCreated = true;
            } else {
                $map->product_id = (int)$product->id;
                $map->sku_snapshot = $dto->sku;
            }

            if (!$map->save()) {
                throw new DomainException('Failed to save product external map: ' . Json::encode($map->getFirstErrors(), JSON_UNESCAPED_UNICODE));
            }

            return [
                'created' => $created,
                'mappingCreated' => $mappingCreated,
                'productId' => (int)$product->id,
                'externalId' => (string)$dto->externalId,
            ];
        });
    }

    private function findExistingProductForLinking(KeyCrmProductDto $dto): ?ProductModel
    {
        if ($dto->sku !== null && $dto->sku !== '') {
            $product = ProductModel::find()->bySku($dto->sku)->one();
            if ($product instanceof ProductModel) {
                return $product;
            }
        }

        return null;
    }

    private function fillProduct(ProductModel $product, KeyCrmProductDto $dto): void
    {
        $product->name = $dto->name;
        $product->slug = $this->makeUniqueSlug($dto);
        $product->sku = $dto->sku;
        $product->barcode = $dto->barcode;
        $product->description = $dto->description;
        $product->price = $dto->price;
        $product->currency = $dto->currencyCode;
        $product->purchased_price = $dto->purchasedPrice;
        $product->quantity = $dto->quantity;
        $product->thumbnail_url = $dto->thumbnailUrl;
        $product->category_external_id = $dto->categoryId !== null ? (string)$dto->categoryId : null;
        $product->is_archived = $dto->isArchived ? 1 : 0;

        // До подтверждения единиц измерения KeyCRM не трогаем локальные поля веса/размеров.
        // $product->weight_kg = ...
        // $product->length_mm = ...
        // $product->width_mm = ...
        // $product->height_mm = ...
    }

    private function makeUniqueSlug(KeyCrmProductDto $dto): string
    {
        $base = Inflector::slug($dto->name);
        if ($base === '') {
            $base = 'product';
        }

        return sprintf('%s-keycrm-%d', $base, $dto->externalId);
    }
}