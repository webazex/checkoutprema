<?php

namespace frontend\services\navigation;

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use Yii;
use yii\helpers\Url;

final class BreadcrumbsProvider
{
    public function __construct(
        private readonly NavigationProvider $navigationProvider = new NavigationProvider(),
    )
    {
    }

    public function forCatalogIndex(bool $absolute = true): array
    {
        return $this->forNavigationKey(NavigationProvider::KEY_CATALOG, $absolute);
    }

    public function forNavigationKey(string $key, bool $absolute = true): array
    {
        $path = $this->navigationProvider->findPathByKey($key);

        if ($path === null) {
            return $this->homeOnly($absolute);
        }

        return $this->buildFromPath($path, $absolute);
    }

    private function homeOnly(bool $absolute): array
    {
        return [
            $this->homeItem($absolute),
        ];
    }

    private function homeItem(bool $absolute): array
    {
        return [
            'label' => Yii::t('frontend', 'Головна'),
            'url' => Url::home($absolute),
        ];
    }

    private function buildFromPath(array $path, bool $absolute): array
    {
        $breadcrumbs = [
            $this->homeItem($absolute),
        ];

        foreach ($path as $item) {
            if (empty($item['breadcrumbs'])) {
                continue;
            }

            if (!empty($item['isExternal'])) {
                continue;
            }

            $breadcrumbs[] = [
                'label' => (string)$item['label'],
                'url' => $this->normalizeUrl((string)$item['url'], $absolute),
            ];
        }

        return $breadcrumbs;
    }

    private function normalizeUrl(string $url, bool $absolute): string
    {
        if (!$absolute) {
            return $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return Url::to($url, true);
    }

    public function forProduct(
        CatalogCategoryModel $category,
        ProductModel         $product,
        bool                 $absolute = true
    ): array
    {
        $breadcrumbs = $this->forCatalogCategory($category, $absolute);

        $breadcrumbs[] = [
            'label' => (string)$product->name,
            'url' => Url::to([
                '/catalog/product',
                'categorySlug' => $category->slug,
                'productSlug' => $product->slug,
            ], $absolute),
        ];

        return $breadcrumbs;
    }

    public function forCatalogCategory(CatalogCategoryModel $category, bool $absolute = true): array
    {
        $key = $this->navigationProvider->getCatalogCategoryKey((int)$category->id);
        $path = $this->navigationProvider->findPathByKey($key);

        if ($path === null) {
            return [
                $this->homeItem($absolute),
                [
                    'label' => Yii::t('frontend', 'Каталог'),
                    'url' => Url::to(['/catalog/index'], $absolute),
                ],
                [
                    'label' => (string)$category->name,
                    'url' => Url::to([
                        '/catalog/category',
                        'categorySlug' => $category->slug,
                    ], $absolute),
                ],
            ];
        }

        return $this->buildFromPath($path, $absolute);
    }
}