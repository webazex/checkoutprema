<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\integrations\keycrm\KeyCrmApiClient;
use common\integrations\keycrm\dto\KeyCrmProductDto;
use common\integrations\keycrm\mappers\KeyCrmProductMapper;
use common\models\meta\MetaModel;
use common\models\product\ProductExternalMapModel;
use common\models\product\ProductModel;
use DomainException;
use yii\helpers\Inflector;
use yii\helpers\Json;

final class KeyCrmProductImportService
{
    private const PRODUCT_CUSTOM_FIELD_META_PREFIX = 'keycrm.custom.';
    public function __construct(
        private readonly KeyCrmApiClient $apiClient,
        private readonly KeyCrmProductMapper $productMapper,
    ) {
    }

    public function importAllProducts(bool $withCustomFields = true, ?int $maxPages = null): array
    {
        $stats = $this->createStats();
        $remoteProducts = [];

        foreach ($this->iterateRemotePages($withCustomFields, $maxPages, $stats) as $pageProducts) {
            foreach ($pageProducts as $dto) {
                $remoteProducts[] = $dto;
            }
        }

        $seenExternalIds = $this->extractSeenExternalIds($remoteProducts);

        $this->upsertRemoteProducts($remoteProducts, $stats);
        $this->archiveMissingProducts($seenExternalIds, $stats);

        return $stats;
    }

    private function createStats(): array
    {
        return [
            'pages' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'mapped' => 0,
            'archived' => 0,
            'restored' => 0,
            'unchanged' => 0,
            'orphanRepaired' => 0,
            'orphanRemoved' => 0,
            'errors' => 0,
        ];
    }

    private function iterateRemotePages(bool $withCustomFields, ?int $maxPages, array &$stats): \Generator
    {
        for ($page = 1; ; $page++) {
            if ($maxPages !== null && $maxPages > 0 && $page > $maxPages) {
                break;
            }

            $response = $this->apiClient->get('/products', [
                'include' => $withCustomFields ? 'custom_fields' : null,
                'page' => $page,
            ]);

            $items = is_array($response['data'] ?? null) ? $response['data'] : [];
            $products = $this->productMapper->mapMany($items);

            $stats['pages']++;

            yield $products;

            if (empty($response['next_page_url'])) {
                break;
            }
        }
    }

    private function extractSeenExternalIds(array $products): array
    {
        $ids = [];

        foreach ($products as $dto) {
            $ids[] = (string) $dto->externalId;
        }

        return array_values(array_unique($ids));
    }

    private function upsertRemoteProducts(array $products, array &$stats): void
    {
        foreach ($products as $dto) {
            try {
                $result = $this->upsertProduct($dto);

                $stats['processed']++;

                if (!empty($result['created'])) {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }

                if (!empty($result['mappingCreated'])) {
                    $stats['mapped']++;
                }

                if (!empty($result['restored'])) {
                    $stats['restored']++;
                }

                if (!empty($result['unchanged'])) {
                    $stats['unchanged']++;
                }

                if (!empty($result['orphanRepaired'])) {
                    $stats['orphanRepaired']++;
                }

                if (!empty($result['orphanRemoved'])) {
                    $stats['orphanRemoved']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                throw $e;
            }
        }
    }

    private function archiveMissingProducts(array $seenExternalIds, array &$stats): void
    {
        $mappings = ProductExternalMapModel::find()
            ->alias('pem')
            ->where(['pem.external_source' => ProductExternalMapModel::SOURCE_KEYCRM])
            ->with('product')
            ->all();

        $seenLookup = array_fill_keys($seenExternalIds, true);
        $now = time();

        /** @var ProductExternalMapModel $mapping */
        foreach ($mappings as $mapping) {
            $externalId = (string) $mapping->external_id;

            if (isset($seenLookup[$externalId])) {
                continue;
            }

            $product = $mapping->product;
            if (!$product instanceof ProductModel) {
                continue;
            }

            if ((int) $product->is_archived === 1) {
                continue;
            }

            $product->is_archived = 1;
            $product->archived_at = $now;

            if (!$product->save()) {
                throw new DomainException(
                    'Failed to archive missing product: ' . Json::encode($product->errors)
                );
            }

            $stats['archived']++;
        }
    }

    private function upsertProduct(KeyCrmProductDto $dto): array
    {
        $created = false;
        $mappingCreated = false;
        $restored = false;
        $unchanged = false;
        $orphanRepaired = false;
        $orphanRemoved = false;

        $externalId = (string) $dto->externalId;
        $source = ProductExternalMapModel::SOURCE_KEYCRM;

        /** @var ProductExternalMapModel|null $mapping */
        $mapping = ProductExternalMapModel::find()
            ->where([
                'external_source' => $source,
                'external_id' => $externalId,
            ])
            ->one();

        /** @var ProductModel|null $product */
        $product = null;

        if ($mapping !== null) {
            $product = $mapping->product;

            if (!$product instanceof ProductModel) {
                $product = $this->findExistingProductForLinking($dto);

                if ($product instanceof ProductModel) {
                    $mapping->product_id = (int) $product->id;

                    if (!$mapping->save()) {
                        throw new DomainException(
                            'Failed to repair product external map: ' . Json::encode($mapping->errors)
                        );
                    }

                    $orphanRepaired = true;
                } else {
                    if ($mapping->delete() === false) {
                        throw new DomainException(
                            'Failed to remove orphan product external map for external_id=' . $externalId
                        );
                    }

                    $mapping = null;
                    $orphanRemoved = true;
                }
            }
        }

        if (!$product instanceof ProductModel) {
            $product = $this->findExistingProductForLinking($dto);
        }

        if (!$product instanceof ProductModel) {
            $product = new ProductModel();
            $created = true;
        }

        $wasArchived = (int) ($product->is_archived ?? 0) === 1;

        $this->fillProduct($product, $dto);
        $this->applyArchiveState($product, $dto);

        if ($wasArchived && (int) $product->is_archived === 0) {
            $restored = true;
        }

        if (!$product->save()) {
            throw new DomainException(
                'Failed to save product: ' . Json::encode($product->errors)
            );
        }
        $this->syncProductCustomFields($product, $dto);

        if (!$mapping instanceof ProductExternalMapModel) {
            $mapping = new ProductExternalMapModel();
            $mapping->external_source = $source;
            $mapping->external_id = $externalId;
            $mapping->product_id = (int) $product->id;

            if (!$mapping->save()) {
                throw new DomainException(
                    'Failed to save product external map: ' . Json::encode($mapping->errors)
                );
            }

            $mappingCreated = true;
        } else {
            if ((int) $mapping->product_id !== (int) $product->id) {
                $mapping->product_id = (int) $product->id;

                if (!$mapping->save()) {
                    throw new DomainException(
                        'Failed to update product external map: ' . Json::encode($mapping->errors)
                    );
                }
            }
        }

        return [
            'created' => $created,
            'mappingCreated' => $mappingCreated,
            'restored' => $restored,
            'unchanged' => $unchanged,
            'orphanRepaired' => $orphanRepaired,
            'orphanRemoved' => $orphanRemoved,
        ];
    }

    private function findExistingProductForLinking(KeyCrmProductDto $dto): ?ProductModel
    {
        if ($dto->sku !== null && trim($dto->sku) !== '') {
            $product = ProductModel::find()
                ->bySku($dto->sku)
                ->one();

            if ($product instanceof ProductModel) {
                return $product;
            }
        }

        return null;
    }

    private function applyArchiveState(ProductModel $product, KeyCrmProductDto $dto): void
    {
        if ($dto->isArchived) {
            $product->is_archived = 1;

            if (empty($product->archived_at)) {
                $product->archived_at = time();
            }

            return;
        }

        $product->is_archived = 0;
        $product->archived_at = null;
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
        // product.quantity stores available-to-sell quantity.
        // Do not overwrite it with raw KeyCRM product quantity because it may be
        // total stock without reserved quantity deduction.
        // If KeyCRM product payload contains an explicitly calculated available quantity,
        // we may safely write it. Otherwise stock is updated by stock webhook.
        if ($dto->availableQuantity !== null) {
            $product->quantity = $dto->availableQuantity;
        } elseif ($product->isNewRecord) {
            $product->quantity = 0;
        }
        $product->thumbnail_url = $dto->thumbnailUrl;
        $product->category_external_id = $dto->categoryId !== null ? (string) $dto->categoryId : null;
        $product->is_archived = $dto->isArchived ? 1 : 0;

        // KeyCRM UI: вес — граммы, размеры — сантиметры.
        // Локально: weight_kg и *_mm.
        $product->weight_kg = $this->gramsToKilograms($dto->weight);
        $product->length_mm = $this->centimetersToMillimeters($dto->length);
        $product->width_mm = $this->centimetersToMillimeters($dto->width);
        $product->height_mm = $this->centimetersToMillimeters($dto->height);
    }

    private function syncProductCustomFields(ProductModel $product, KeyCrmProductDto $dto): void
    {
        if (!$product->id || $dto->customFields === []) {
            return;
        }

        foreach ($dto->customFields as $fieldKey => $field) {
            $code = null;
            $value = null;

            if (is_array($field)) {
                $code = $this->extractCustomFieldCode($field, $fieldKey);
                $value = $this->extractCustomFieldValue($field);
            } else {
                $code = $this->normalizeCustomFieldCode((string)$fieldKey);
                $value = $this->stringifyCustomFieldValue($field);
            }

            if ($code === null || $code === '') {
                continue;
            }

            $metaKey = substr(self::PRODUCT_CUSTOM_FIELD_META_PREFIX . $code, 0, 128);

            MetaModel::upsertText(
                MetaModel::ENTITY_PRODUCT,
                (int)$product->id,
                $metaKey,
                $value
            );
        }
    }

    private function extractCustomFieldCode(array $field, int|string|null $fallbackKey = null): ?string
    {
        foreach ([
                     'code',
                     'key',
                     'uuid',
                     'id',
                     'name',
                     'title',
                     'label',
                     'field_name',
                     'field_label',
                 ] as $key) {
            if (array_key_exists($key, $field)) {
                $code = $this->normalizeCustomFieldCode((string)$field[$key]);

                if ($code !== null) {
                    return $code;
                }
            }
        }

        foreach ([
                     ['field', 'code'],
                     ['field', 'key'],
                     ['field', 'uuid'],
                     ['field', 'id'],
                     ['field', 'name'],
                     ['custom_field', 'code'],
                     ['custom_field', 'key'],
                     ['custom_field', 'uuid'],
                     ['custom_field', 'id'],
                     ['custom_field', 'name'],
                 ] as [$parentKey, $childKey]) {
            if (
                isset($field[$parentKey])
                && is_array($field[$parentKey])
                && array_key_exists($childKey, $field[$parentKey])
            ) {
                $code = $this->normalizeCustomFieldCode((string)$field[$parentKey][$childKey]);

                if ($code !== null) {
                    return $code;
                }
            }
        }

        if ($fallbackKey !== null && !is_int($fallbackKey)) {
            return $this->normalizeCustomFieldCode((string)$fallbackKey);
        }

        return null;
    }

    private function extractCustomFieldValue(array $field): ?string
    {
        foreach ([
                     'value',
                     'values',
                     'data',
                     'field_value',
                     'field_values',
                     'selected',
                     'selected_value',
                     'selected_values',
                 ] as $key) {
            if (array_key_exists($key, $field)) {
                return $this->stringifyCustomFieldValue($field[$key]);
            }
        }

        return null;
    }

    private function normalizeCustomFieldCode(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // CT_1001 / CT_1002 сохраняем как есть.
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return $value;
        }

        $slug = Inflector::slug($value);

        return $slug !== '' ? $slug : null;
    }

    private function stringifyCustomFieldValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            $result = trim((string)$value);

            return $result !== '' ? $result : null;
        }

        if (is_array($value)) {
            foreach (['value', 'name', 'title', 'label'] as $key) {
                if (array_key_exists($key, $value)) {
                    return $this->stringifyCustomFieldValue($value[$key]);
                }
            }

            $parts = [];

            foreach ($value as $item) {
                $part = $this->stringifyCustomFieldValue($item);

                if ($part !== null) {
                    $parts[] = $part;
                }
            }

            $result = trim(implode(', ', array_unique($parts)));

            return $result !== '' ? $result : null;
        }

        return null;
    }

    private function gramsToKilograms(?float $value): ?float
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return round($value / 1000, 3);
    }

    private function centimetersToMillimeters(?float $value): ?int
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return (int)round($value * 10);
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