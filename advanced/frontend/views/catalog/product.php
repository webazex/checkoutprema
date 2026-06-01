<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;
use common\models\meta\MetaModel;
use frontend\services\catalog\ProductAccordionBuilder;


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
$t = static fn(string $message, array $params = []): string => Yii::t('frontend', $message, $params);
$accordionItems = (new ProductAccordionBuilder())->build($product);

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

            <?php if (!empty($product->sku)): ?>
                <div class="product-hero__sku">
                    <span class="product-hero__sku-label">
                        <?= Html::encode(Yii::t('frontend', 'SKU')) ?>
                    </span>
                                <span class="product-hero__sku-value">
                        <?= Html::encode($product->sku) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="product-hero__price">
                <?= $price ?>
            </div>

            <?php if ($product->getIsAvailable()): ?>
                <div class="product-hero__actions">
                    <?= Html::beginForm(['/cart/add'], 'post', [
                            'class' => 'product-hero__form js-catalog-add-to-cart',
                            'data-product-name' => $product->name,
                    ]) ?>
                    <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                    <?= Html::hiddenInput('qty', 1) ?>
                    <button class="product-hero__button product-hero__button--secondary" type="submit">
                        <?= Html::encode(Yii::t('frontend', 'Add to cart')) ?>
                    </button>
                    <?= Html::endForm() ?>

                    <?= Html::beginForm(['/cart/buy-now'], 'post', [
                            'class' => 'product-hero__form product-hero__form--buy-now',
                    ]) ?>
                    <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                    <?= Html::hiddenInput('qty', 1) ?>
                    <button class="product-hero__button product-hero__button--primary" type="submit">
                        <?= Html::encode(Yii::t('frontend', 'Buy now')) ?>
                    </button>
                    <?= Html::endForm() ?>
                </div>
            <?php else: ?>
                <div class="product-hero__unavailable">
                    <?= Html::encode(Yii::t('frontend', 'Product is out of stock.')) ?>
                </div>
            <?php endif; ?>

            <?= $this->render('_product_accordion', [
                    'items' => $accordionItems,
            ]) ?>
        </div>
    </section>



    <?php if ($relatedProducts !== []): ?>
        <section class="related-products">
            <h2><?= Html::encode(Yii::t('frontend', 'Related products')) ?></h2>

            <div class="catalog-grid">
                <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <?= $this->render('_product_card', ['product' => $relatedProduct]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
<?=$this->render('__cart-side-box'); ?>