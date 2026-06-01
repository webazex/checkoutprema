<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var array<int, array{category: CatalogCategoryModel, children: array, hasProducts: bool}> $categoryTree */
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

    <?php if ($categoryTree !== []): ?>
        <?= $this->render('_category_tree', [
                'categoryTree' => $categoryTree,
        ]) ?>
    <?php endif; ?>

    <section class="catalog-grid" aria-label="Товари">
        <?php foreach ($products as $product): ?>
            <?= $this->render('_product_card', ['product' => $product]) ?>
        <?php endforeach; ?>
    </section>
    <?=$this->render('__cart-side-box'); ?>
</section>