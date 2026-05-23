<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var CatalogCategoryModel[] $categories */
/** @var ProductModel[] $products */
/** @var array $breadcrumbs */
/** @var string $schemaJson */
/** @var string $breadcrumbSchemaJson */

echo $this->render('_schema', [
    'schemaJson' => $schemaJson,
    'breadcrumbSchemaJson' => $breadcrumbSchemaJson,
]);
?>

<section class="catalog-page">
    <?= $this->render('_breadcrumbs', ['breadcrumbs' => $breadcrumbs]) ?>

    <header class="catalog-page__header">
        <h1>Каталог Prema</h1>
        <p>
            Товари Prema для йоги, пілатесу, спорту, ароматерапії та подарунків.
            Оберіть категорію або перегляньте всі актуальні товари.
        </p>
    </header>

    <?php if ($categories !== []): ?>
        <section class="catalog-categories" aria-label="Категорії товарів">
            <?php foreach ($categories as $category): ?>
                <a class="catalog-category-tile" href="<?= Html::encode(Url::to(['/catalog/category', 'categorySlug' => $category->slug])) ?>">
                    <?php if (!empty($category->thumbnail_url)): ?>
                        <img
                            src="<?= Html::encode($category->thumbnail_url) ?>"
                            alt="<?= Html::encode($category->name) ?>"
                            loading="lazy"
                            width="360"
                            height="240"
                        >
                    <?php endif; ?>

                    <span><?= Html::encode($category->name) ?></span>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="catalog-grid" aria-label="Товари">
        <?php foreach ($products as $product): ?>
            <?= $this->render('_product_card', ['product' => $product]) ?>
        <?php endforeach; ?>
    </section>
</section>