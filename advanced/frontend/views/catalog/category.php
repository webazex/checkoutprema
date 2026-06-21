<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;

/** @var CatalogCategoryModel $category */
/** @var ProductModel[] $products */
/** @var array $breadcrumbs */
/** @var string $schemaJson */
/** @var string $breadcrumbSchemaJson */

echo $this->render('_schema', [
        'schemaJson' => $schemaJson,
        'breadcrumbSchemaJson' => $breadcrumbSchemaJson,
]);
?>

<section class="catalog-page catalog-page--category">
    <?= $this->render('_breadcrumbs', ['breadcrumbs' => $breadcrumbs]) ?>

    <header class="catalog-page__header">
        <h1><?= Html::encode($category->name) ?></h1>

        <?php if (!empty($category->description)): ?>
            <div class="catalog-page__seo-text">
                <?= $category->description ?>
            </div>
        <?php else: ?>
            <p>
                Добірка товарів Prema у категорії «<?= Html::encode($category->name) ?>».
                Обирайте якісні товари з доставкою по Україні.
            </p>
        <?php endif; ?>
    </header>

    <?php if ($products === []): ?>
        <div class="catalog-empty">
            У цій категорії поки немає доступних товарів.
        </div>
    <?php else: ?>
        <section class="catalog-grid" aria-label="<?= Html::encode($category->name) ?>">
            <?php foreach ($products as $product): ?>
                <?= $this->render('_product_card', ['product' => $product]) ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?= $this->render('__cart-side-box'); ?>
</section>