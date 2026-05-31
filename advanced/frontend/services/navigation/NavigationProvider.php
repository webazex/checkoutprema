<?php

namespace frontend\services\navigation;

use common\models\catalog\CatalogCategoryModel;
use Yii;
use yii\helpers\Url;

final class NavigationProvider
{
    public const KEY_CATALOG = 'catalog';
    public const KEY_CATEGORY_PREFIX = 'category.';

    public function getHeaderTree(): array
    {
        $items = Yii::$app->params['navigation']['header'] ?? [];

        return $this->normalizeTree($items);
    }

    public function getFooterTree(): array
    {
        $items = Yii::$app->params['navigation']['footer'] ?? [];

        return $this->normalizeTree($items);
    }

    public function getFullTree(): array
    {
        return [
            'header' => $this->getHeaderTree(),
            'footer' => $this->getFooterTree(),
        ];
    }

    public function getCatalogCategoryKey(int $categoryId): string
    {
        return self::KEY_CATEGORY_PREFIX . $categoryId;
    }

    public function findPathByKey(string $targetKey, ?array $tree = null): ?array
    {
        $tree ??= $this->getHeaderTree();

        return $this->findPathRecursive($targetKey, $tree);
    }

    private function normalizeTree(array $items): array
    {
        $tree = [];

        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['visible'] ?? true) === false) {
                continue;
            }

            $key = (string)$key;
            $type = (string)($item['type'] ?? 'page');
            $isExternal = $type === 'external';

            $children = [];

            if (!empty($item['children']) && is_array($item['children'])) {
                $children = $this->normalizeTree($item['children']);
            }

            if (($item['childrenProvider'] ?? null) === 'catalogCategories') {
                $children = array_replace($children, $this->getCatalogCategoryTree());
            }

            $url = $this->buildUrl($item, $isExternal);

            $normalizedItem = [
                'key' => $key,
                'type' => $type,
                'label' => Yii::t('frontend', (string)($item['label'] ?? '')),
                'url' => $url,
                'route' => $item['route'] ?? null,
                'isExternal' => $isExternal,
                'target' => $item['target'] ?? ($isExternal ? '_blank' : null),
                'rel' => $item['rel'] ?? ($isExternal ? 'noopener noreferrer' : null),
                'isActive' => false,
                'breadcrumbs' => (bool)($item['breadcrumbs'] ?? !$isExternal),
                'children' => $children,

                // На будущее: удобно для БД/админки.
                'entityId' => $item['entityId'] ?? null,
                'entityClass' => $item['entityClass'] ?? null,
                'slug' => $item['slug'] ?? null,
            ];

            $normalizedItem['isActive'] = $this->isItemActive($normalizedItem);

            $tree[$key] = $normalizedItem;
        }

        return $tree;
    }

    private function buildUrl(array $item, bool $isExternal): string
    {
        if ($isExternal) {
            return (string)($item['url'] ?? '#');
        }

        if (!empty($item['route'])) {
            return Url::to($item['route']);
        }

        return (string)($item['url'] ?? '#');
    }

    private function isItemActive(array $item): bool
    {
        if (($item['isExternal'] ?? false) === true) {
            return false;
        }

        if ($this->isUrlActive((string)($item['url'] ?? ''))) {
            return true;
        }

        foreach (($item['children'] ?? []) as $child) {
            if (!empty($child['isActive'])) {
                return true;
            }
        }

        return false;
    }

    private function isUrlActive(string $url): bool
    {
        $currentPath = trim(Yii::$app->request->pathInfo, '/');

        $path = parse_url($url, PHP_URL_PATH);
        $path = trim((string)$path, '/');

        return $currentPath === $path;
    }

    private function getCatalogCategoryTree(): array
    {
        /** @var CatalogCategoryModel[] $categories */
        $categories = CatalogCategoryModel::find()
            ->active()
            ->ordered()
            ->all();

        $groupedByParent = [];

        foreach ($categories as $category) {
            $parentKey = $category->parent_id === null ? 0 : (int)$category->parent_id;
            $groupedByParent[$parentKey][] = $category;
        }

        return $this->buildCatalogCategoryBranch($groupedByParent, 0);
    }

    /**
     * @param array<int, CatalogCategoryModel[]> $groupedByParent
     */
    private function buildCatalogCategoryBranch(array $groupedByParent, int $parentId): array
    {
        $branch = [];

        foreach (($groupedByParent[$parentId] ?? []) as $category) {
            $categoryId = (int)$category->id;
            $key = $this->getCatalogCategoryKey($categoryId);

            $children = $this->buildCatalogCategoryBranch($groupedByParent, $categoryId);

            $url = Url::to([
                '/catalog/category',
                'categorySlug' => $category->slug,
            ]);

            $branch[$key] = [
                'key' => $key,
                'type' => 'catalog_category',
                'label' => (string)$category->name,
                'url' => $url,
                'route' => [
                    '/catalog/category',
                    'categorySlug' => $category->slug,
                ],
                'isExternal' => false,
                'target' => null,
                'rel' => null,
                'isActive' => $this->isUrlActive($url),
                'breadcrumbs' => true,
                'children' => $children,
                'entityId' => $categoryId,
                'entityClass' => CatalogCategoryModel::class,
                'slug' => (string)$category->slug,
            ];
        }

        return $branch;
    }

    private function findPathRecursive(string $targetKey, array $tree, array $path = []): ?array
    {
        foreach ($tree as $key => $item) {
            $currentPath = array_merge($path, [$item]);

            if ((string)$key === $targetKey) {
                return $currentPath;
            }

            if (!empty($item['children']) && is_array($item['children'])) {
                $found = $this->findPathRecursive($targetKey, $item['children'], $currentPath);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}