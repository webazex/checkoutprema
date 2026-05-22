<?php


declare(strict_types=1);

namespace common\services\keycrm;

use common\integrations\keycrm\KeyCrmApiClient;
use common\integrations\keycrm\dto\KeyCrmCategoryDto;
use common\integrations\keycrm\mappers\KeyCrmCategoryMapper;
use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use DomainException;
use Throwable;
use Yii;
use yii\helpers\Inflector;
use yii\helpers\Json;

final class KeyCrmCategorySyncService
{
    public function __construct(
        private readonly KeyCrmApiClient      $apiClient,
        private readonly KeyCrmCategoryMapper $categoryMapper,
    )
    {
    }

    public function importAllCategories(?int $maxPages = null): array
    {
        $stats = [
            'pages' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'archived' => 0,
            'parentsLinked' => 0,
            'missingParents' => 0,
            'errors' => 0,
        ];

        $remoteCategories = [];

        foreach ($this->iterateRemotePages($maxPages, $stats) as $pageCategories) {
            foreach ($pageCategories as $categoryDto) {
                $remoteCategories[] = $categoryDto;
            }
        }

        $seenExternalIds = [];

        foreach ($remoteCategories as $categoryDto) {
            $seenExternalIds[] = (string)$categoryDto->externalId;

            try {
                $result = $this->upsertCategory($categoryDto);

                $stats['processed']++;

                if ($result['created']) {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;

                Yii::error([
                    'message' => 'Failed to sync KeyCRM category.',
                    'externalId' => $categoryDto->externalId,
                    'exception' => $e->getMessage(),
                ], __METHOD__);

                throw $e;
            }
        }

        $parentStats = $this->linkParents($remoteCategories);

        $stats['parentsLinked'] = $parentStats['linked'];
        $stats['missingParents'] = $parentStats['missing'];
        $stats['archived'] = $this->archiveMissingCategories($seenExternalIds);

        return $stats;
    }

    public function linkProductsToCategories(): array
    {
        $stats = [
            'scanned' => 0,
            'linked' => 0,
            'unchanged' => 0,
            'missingCategory' => 0,
            'emptyExternalCategory' => 0,
            'errors' => 0,
        ];

        /** @var ProductModel[] $products */
        $products = ProductModel::find()
            ->where(['not', ['category_external_id' => null]])
            ->all();

        foreach ($products as $product) {
            $stats['scanned']++;

            $externalId = trim((string)$product->category_external_id);

            if ($externalId === '') {
                $stats['emptyExternalCategory']++;
                continue;
            }

            /** @var CatalogCategoryModel|null $category */
            $category = CatalogCategoryModel::find()
                ->byExternal(CatalogCategoryModel::SOURCE_KEYCRM, $externalId)
                ->one();

            if (!$category instanceof CatalogCategoryModel) {
                $stats['missingCategory']++;
                continue;
            }

            if ((int)$product->category_id === (int)$category->id) {
                $stats['unchanged']++;
                continue;
            }

            $product->category_id = (int)$category->id;

            if (!$product->save()) {
                $stats['errors']++;

                throw new DomainException(
                    'Failed to link product category. Product ID: ' .
                    (int)$product->id .
                    '. Errors: ' .
                    Json::encode($product->errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }

            $stats['linked']++;
        }

        return $stats;
    }

    /**
     * @return \Generator<int, KeyCrmCategoryDto[]>
     */
    private function iterateRemotePages(?int $maxPages, array &$stats): \Generator
    {
        for ($page = 1; ; $page++) {
            if ($maxPages !== null && $maxPages > 0 && $page > $maxPages) {
                break;
            }

            $response = $this->apiClient->get('/products/categories', [
                'page' => $page,
            ]);

            $items = $this->extractItems($response);
            $categories = $this->categoryMapper->mapMany($items);

            $stats['pages']++;

            yield $categories;

            if (!$this->hasNextPage($response)) {
                break;
            }
        }
    }

    private function extractItems(array $response): array
    {
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        if (array_is_list($response)) {
            return $response;
        }

        return [];
    }

    private function hasNextPage(array $response): bool
    {
        if (!empty($response['next_page_url'])) {
            return true;
        }

        $currentPage = (int)($response['current_page'] ?? 0);
        $lastPage = (int)($response['last_page'] ?? 0);

        return $currentPage > 0 && $lastPage > 0 && $currentPage < $lastPage;
    }

    private function upsertCategory(KeyCrmCategoryDto $dto): array
    {
        $created = false;

        /** @var CatalogCategoryModel|null $category */
        $category = CatalogCategoryModel::find()
            ->byExternal(CatalogCategoryModel::SOURCE_KEYCRM, (string)$dto->externalId)
            ->one();

        if (!$category instanceof CatalogCategoryModel) {
            $category = new CatalogCategoryModel();
            $category->external_source = CatalogCategoryModel::SOURCE_KEYCRM;
            $category->external_id = (string)$dto->externalId;
            $category->slug = $this->makeUniqueSlug($dto);
            $created = true;
        }

        $category->name = $dto->name;
        $category->parent_external_id = $dto->parentExternalId !== null ? (string)$dto->parentExternalId : null;
        $category->description = $dto->description;
        $category->thumbnail_url = $dto->thumbnailUrl;
        $category->sort_order = $dto->sortOrder;
        $category->is_archived = $dto->isArchived ? 1 : 0;
        $category->is_active = $dto->isArchived ? 0 : 1;
        $category->raw_payload = $dto->raw;

        if (!$category->save()) {
            throw new DomainException(
                'Failed to save catalog category. External ID: ' .
                $dto->externalId .
                '. Errors: ' .
                Json::encode($category->errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        return [
            'created' => $created,
        ];
    }

    /**
     * @param KeyCrmCategoryDto[] $remoteCategories
     */
    private function linkParents(array $remoteCategories): array
    {
        $stats = [
            'linked' => 0,
            'missing' => 0,
        ];

        foreach ($remoteCategories as $dto) {
            if ($dto->parentExternalId === null) {
                continue;
            }

            /** @var CatalogCategoryModel|null $category */
            $category = CatalogCategoryModel::find()
                ->byExternal(CatalogCategoryModel::SOURCE_KEYCRM, (string)$dto->externalId)
                ->one();

            if (!$category instanceof CatalogCategoryModel) {
                continue;
            }

            /** @var CatalogCategoryModel|null $parent */
            $parent = CatalogCategoryModel::find()
                ->byExternal(CatalogCategoryModel::SOURCE_KEYCRM, (string)$dto->parentExternalId)
                ->one();

            if (!$parent instanceof CatalogCategoryModel) {
                $stats['missing']++;
                continue;
            }

            if ((int)$category->parent_id === (int)$parent->id) {
                continue;
            }

            $category->parent_id = (int)$parent->id;

            if (!$category->save()) {
                throw new DomainException(
                    'Failed to link category parent. Category external ID: ' .
                    $dto->externalId .
                    '. Errors: ' .
                    Json::encode($category->errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }

            $stats['linked']++;
        }

        return $stats;
    }

    /**
     * @param string[] $seenExternalIds
     */
    private function archiveMissingCategories(array $seenExternalIds): int
    {
        if ($seenExternalIds === []) {
            return 0;
        }

        $seenLookup = array_fill_keys($seenExternalIds, true);
        $archived = 0;

        /** @var CatalogCategoryModel[] $categories */
        $categories = CatalogCategoryModel::find()
            ->where(['external_source' => CatalogCategoryModel::SOURCE_KEYCRM])
            ->all();

        foreach ($categories as $category) {
            $externalId = (string)$category->external_id;

            if (isset($seenLookup[$externalId])) {
                continue;
            }

            if ((int)$category->is_archived === 1) {
                continue;
            }

            $category->is_archived = 1;
            $category->is_active = 0;

            if (!$category->save()) {
                throw new DomainException(
                    'Failed to archive missing category. Category ID: ' .
                    (int)$category->id .
                    '. Errors: ' .
                    Json::encode($category->errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }

            $archived++;
        }

        return $archived;
    }

    private function makeUniqueSlug(KeyCrmCategoryDto $dto): string
    {
        $base = Inflector::slug($dto->name);

        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;

        if (!$this->slugExists($slug)) {
            return $slug;
        }

        $slug = sprintf('%s-keycrm-%d', $base, $dto->externalId);

        if (!$this->slugExists($slug)) {
            return $slug;
        }

        $i = 2;

        while (true) {
            $slug = sprintf('%s-keycrm-%d-%d', $base, $dto->externalId, $i);

            if (!$this->slugExists($slug)) {
                return $slug;
            }

            $i++;
        }
    }

    private function slugExists(string $slug): bool
    {
        return CatalogCategoryModel::find()
            ->where(['slug' => $slug])
            ->exists();
    }
}