<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use Yii;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\View;
use frontend\services\navigation\BreadcrumbsProvider;

final class CatalogController extends Controller
{
    public function actionIndex(): string
    {
        $categoryTree = $this->findVisibleCategoryTree();

        $products = ProductModel::find()
            ->notArchived()
            ->with('category')
            ->ordered()
            ->all();

        $canonicalUrl = Url::to(['/catalog/index'], true);

        $this->registerPageMeta(
            title: 'Каталог товарів Prema — купити в Україні | Prema',
            description: 'Каталог товарів Prema: килимки для йоги та пілатесу, аксесуари, спортивний інвентар, ароматерапія та подарункові набори з доставкою по Україні.',
            canonicalUrl: $canonicalUrl,
            ogImage: $this->getFallbackOgImage($products)
        );

        $breadcrumbs = $this->breadcrumbsProvider()->forCatalogCategory($category, true);

        return $this->render('index', [
            'categoryTree' => $categoryTree,
            'products' => $products,
            'breadcrumbs' => $breadcrumbs,
            'schemaJson' => $this->buildItemListSchema($products),
            'breadcrumbSchemaJson' => $this->buildBreadcrumbSchema($breadcrumbs),
        ]);
    }

    public function actionCategory(string $categorySlug): string
    {
        $category = $this->findCategoryBySlug($categorySlug);

        $products = ProductModel::find()
            ->notArchived()
            ->byCategoryId((int)$category->id)
            ->with('category')
            ->ordered()
            ->all();

        $canonicalUrl = Url::to([
            '/catalog/category',
            'categorySlug' => $category->slug,
        ], true);

        $title = $category->seo_title ?: $this->limitText(
            $category->name . ' — купити в Україні | Prema',
            60
        );

        $description = $category->seo_description ?: $this->limitText(
            $category->name . ' від Prema. Якісні товари для йоги, спорту та щоденних практик. Замовляйте онлайн з доставкою по Україні.',
            155
        );

        $this->registerPageMeta(
            title: $title,
            description: $description,
            canonicalUrl: $canonicalUrl,
            ogImage: $category->thumbnail_url ?: $this->getFallbackOgImage($products)
        );

        $breadcrumbs = [
            ['label' => 'Головна', 'url' => Url::to(['/site/index'], true)],
            ['label' => 'Каталог', 'url' => Url::to(['/catalog/index'], true)],
            ['label' => $category->name, 'url' => $canonicalUrl],
        ];

        return $this->render('category', [
            'category' => $category,
            'products' => $products,
            'breadcrumbs' => $breadcrumbs,
            'schemaJson' => $this->buildItemListSchema($products),
            'breadcrumbSchemaJson' => $this->buildBreadcrumbSchema($breadcrumbs),
        ]);
    }

    public function actionProduct(string $categorySlug, string $productSlug): string
    {
        $category = $this->findCategoryBySlug($categorySlug);

        /** @var ProductModel|null $product */
        $product = ProductModel::find()
            ->notArchived()
            ->bySlug($productSlug)
            ->byCategoryId((int)$category->id)
            ->with('category')
            ->one();

        if (!$product instanceof ProductModel) {
            throw new NotFoundHttpException('Товар не знайдено.');
        }

        $relatedProducts = ProductModel::find()
            ->notArchived()
            ->byCategoryId((int)$category->id)
            ->andWhere(['<>', 'id', (int)$product->id])
            ->limit(4)
            ->ordered()
            ->all();

        $canonicalUrl = Url::to([
            '/catalog/product',
            'categorySlug' => $category->slug,
            'productSlug' => $product->slug,
        ], true);

        $price = $this->formatPrice($product);

        $title = $this->limitText(
            $product->name . ' — ' . $price . ' | Prema',
            60
        );

        $description = $this->limitText(
            $this->plainText($product->description)
                ?: $product->name . ' від Prema. Купити онлайн з доставкою по Україні.',
            155
        );

        $this->registerPageMeta(
            title: $title,
            description: $description,
            canonicalUrl: $canonicalUrl,
            ogImage: $product->thumbnail_url,
            ogType: 'product'
        );

        $breadcrumbs = $this->breadcrumbsProvider()->forProduct($category, $product, true);

        return $this->render('product', [
            'category' => $category,
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'breadcrumbs' => $breadcrumbs,
            'schemaJson' => $this->buildProductSchema($product, $canonicalUrl),
            'breadcrumbSchemaJson' => $this->buildBreadcrumbSchema($breadcrumbs),
        ]);
    }

    /**
     * Возвращает дерево категорий для публичного вывода.
     *
     * Категория попадает в дерево, если:
     * - она активна;
     * - не архивная;
     * - в ней есть хотя бы один неархивный товар
     *   ИЛИ у неё есть дочерние категории, которые прошли это же правило.
     *
     * Наличие товара на складе здесь НЕ учитываем.
     * Товары "немає в наявності" остаются на сайте.
     *
     * @return array<int, array{category: CatalogCategoryModel, children: array, hasProducts: bool}>
     */
    private function findVisibleCategoryTree(): array
    {
        /** @var CatalogCategoryModel[] $categories */
        $categories = CatalogCategoryModel::find()
            ->active()
            ->ordered()
            ->all();

        $productCategoryIds = ProductModel::find()
            ->notArchived()
            ->select('category_id')
            ->andWhere(['not', ['category_id' => null]])
            ->groupBy('category_id')
            ->column();

        $productCategoryLookup = array_fill_keys(
            array_map('intval', $productCategoryIds),
            true
        );

        $categoriesByParent = [];

        foreach ($categories as $category) {
            $parentId = $category->parent_id !== null ? (int)$category->parent_id : 0;
            $categoriesByParent[$parentId][] = $category;
        }

        $buildTree = function (int $parentId) use (&$buildTree, $categoriesByParent, $productCategoryLookup): array {
            $tree = [];

            foreach ($categoriesByParent[$parentId] ?? [] as $category) {
                $categoryId = (int)$category->id;
                $children = $buildTree($categoryId);
                $hasProducts = isset($productCategoryLookup[$categoryId]);

                if (!$hasProducts && $children === []) {
                    continue;
                }

                $tree[] = [
                    'category' => $category,
                    'children' => $children,
                    'hasProducts' => $hasProducts,
                ];
            }

            return $tree;
        };

        return $buildTree(0);
    }

    private function findCategoryBySlug(string $slug): CatalogCategoryModel
    {
        /** @var CatalogCategoryModel|null $category */
        $category = CatalogCategoryModel::find()
            ->active()
            ->bySlug($slug)
            ->one();

        if (!$category instanceof CatalogCategoryModel) {
            throw new NotFoundHttpException('Категорію не знайдено.');
        }

        return $category;
    }

    private function registerPageMeta(
        string $title,
        string $description,
        string $canonicalUrl,
        ?string $ogImage = null,
        string $ogType = 'website'
    ): void {
        $view = $this->getView();

        $view->title = $title;

        $view->registerMetaTag(['name' => 'description', 'content' => $description], 'description');
        $view->registerMetaTag(['name' => 'robots', 'content' => 'index, follow'], 'robots');

        $view->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl], 'canonical');

        $view->registerMetaTag(['property' => 'og:title', 'content' => $title], 'og:title');
        $view->registerMetaTag(['property' => 'og:description', 'content' => $description], 'og:description');
        $view->registerMetaTag(['property' => 'og:url', 'content' => $canonicalUrl], 'og:url');
        $view->registerMetaTag(['property' => 'og:type', 'content' => $ogType], 'og:type');

        if ($ogImage !== null && trim($ogImage) !== '') {
            $view->registerMetaTag(['property' => 'og:image', 'content' => $ogImage], 'og:image');
            $view->registerMetaTag(['name' => 'twitter:image', 'content' => $ogImage], 'twitter:image');
        }

        $view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image'], 'twitter:card');
        $view->registerMetaTag(['name' => 'twitter:title', 'content' => $title], 'twitter:title');
        $view->registerMetaTag(['name' => 'twitter:description', 'content' => $description], 'twitter:description');
    }

    private function buildBreadcrumbSchema(array $breadcrumbs): string
    {
        $items = [];

        foreach ($breadcrumbs as $position => $item) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['label'],
                'item' => $item['url'],
            ];
        }

        return Json::htmlEncode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    /**
     * @param ProductModel[] $products
     */
    private function buildItemListSchema(array $products): string
    {
        $items = [];

        foreach ($products as $position => $product) {
            $category = $product->category;

            if (!$category instanceof CatalogCategoryModel || empty($product->slug)) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'url' => Url::to([
                    '/catalog/product',
                    'categorySlug' => $category->slug,
                    'productSlug' => $product->slug,
                ], true),
                'name' => $product->name,
            ];
        }

        return Json::htmlEncode([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => $items,
        ]);
    }

    private function buildProductSchema(ProductModel $product, string $canonicalUrl): string
    {
        $availability = $product->getIsAvailable()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        return Json::htmlEncode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'image' => $product->thumbnail_url ? [$product->thumbnail_url] : [],
            'description' => $this->plainText($product->description) ?: $product->name,
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Prema',
            ],
            'sku' => $product->sku,
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format((float)$product->price, 2, '.', ''),
                'priceCurrency' => $product->currency ?: 'UAH',
                'availability' => $availability,
                'url' => $canonicalUrl,
            ],
        ]);
    }

    /**
     * @param ProductModel[] $products
     */
    private function getFallbackOgImage(array $products): ?string
    {
        foreach ($products as $product) {
            if (!empty($product->thumbnail_url)) {
                return $product->thumbnail_url;
            }
        }

        return null;
    }

    private function formatPrice(ProductModel $product): string
    {
        $currency = $product->currency ?: 'UAH';

        return number_format((float)$product->price, 0, '.', ' ') . ' ' . $currency;
    }

    private function plainText(?string $value): string
    {
        return trim(strip_tags((string)$value));
    }

    private function limitText(string $value, int $limit): string
    {
        return StringHelper::truncate($this->plainText($value), $limit, '');
    }

    private function breadcrumbsProvider(): BreadcrumbsProvider
    {
        return new BreadcrumbsProvider();
    }
}