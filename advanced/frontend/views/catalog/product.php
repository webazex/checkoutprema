<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;

/** @var CatalogCategoryModel $category */
/** @var ProductModel $product */
/** @var ProductModel[] $relatedProducts */
/** @var array $breadcrumbs */
/** @var string $schemaJson */
/** @var string $breadcrumbSchemaJson */

echo $this->render('_schema', [
    'schemaJson' => $schemaJson,
    'breadcrumbSchemaJson' => $breadcrumbSchemaJson,
]);

$price = number_format((float)$product->price, 0, '.', ' ') . ' ' . Html::encode($product->currency ?: 'UAH');
$alt = trim($product->name . ', ' . $category->name);
?>

<article class="product-page">
    <?= $this->render('_breadcrumbs', ['breadcrumbs' => $breadcrumbs]) ?>

    <section class="product-hero">
        <div class="product-hero__image-wrap">
            <?php if (!empty($product->thumbnail_url)): ?>
                <img
                    class="product-hero__image"
                    src="<?= Html::encode($product->thumbnail_url) ?>"
                    alt="<?= Html::encode($alt) ?>"
                    width="720"
                    height="720"
                >
            <?php else: ?>
                <div class="product-hero__image product-hero__image--empty">
                    <?= Html::encode($product->name) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-hero__content">
            <div class="product-hero__category">
                <?= Html::encode($category->name) ?>
            </div>

            <h1><?= Html::encode($product->name) ?></h1>

            <div class="product-hero__price">
                <?= $price ?>
            </div>

            <?php if ($product->getIsAvailable()): ?>
                <?= Html::beginForm(['/cart/add'], 'post', ['class' => 'product-hero__form']) ?>
                <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                <?= Html::hiddenInput('qty', 1) ?>
                <button class="product-hero__button" type="submit">Додати в кошик</button>
                <?= Html::endForm() ?>
            <?php else: ?>
                <div class="product-hero__unavailable">Немає в наявності</div>
            <?php endif; ?>

            <table class="product-specs">
                <tbody>
                <?php if (!empty($product->sku)): ?>
                    <tr>
                        <th>Артикул</th>
                        <td><?= Html::encode($product->sku) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if (!empty($product->barcode)): ?>
                    <tr>
                        <th>Штрихкод</th>
                        <td><?= Html::encode($product->barcode) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if (!empty($product->weight_kg)): ?>
                    <tr>
                        <th>Вага</th>
                        <td><?= Html::encode((string)$product->weight_kg) ?> кг</td>
                    </tr>
                <?php endif; ?>

                <?php if (!empty($product->length_mm) || !empty($product->width_mm) || !empty($product->height_mm)): ?>
                    <tr>
                        <th>Розмір</th>
                        <td>
                            <?= Html::encode(trim(
                                (string)$product->length_mm . ' × ' .
                                (string)$product->width_mm . ' × ' .
                                (string)$product->height_mm,
                                " ×"
                            )) ?> мм
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="product-description">
        <h2>Опис товару</h2>

        <?php if (!empty($product->description)): ?>
            <div class="product-description__text">
                <?= $product->description ?>
            </div>
        <?php else: ?>
            <p>
                <?= Html::encode($product->name) ?> — товар Prema у категорії
                «<?= Html::encode($category->name) ?>». Детальний опис буде додано після підготовки SEO-текстів.
            </p>
        <?php endif; ?>
    </section>

    <?php if ($relatedProducts !== []): ?>
        <section class="related-products">
            <h2>Схожі товари</h2>

            <div class="catalog-grid">
                <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <?= $this->render('_product_card', ['product' => $relatedProduct]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>