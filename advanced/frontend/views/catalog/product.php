<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use frontend\helpers\catalog\ProductPageViewHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogCategoryModel $category */
/** @var ProductModel $product */
/** @var ProductModel[] $relatedProducts */
/** @var array $breadcrumbs */
/** @var string $schemaJson */
/** @var string $breadcrumbSchemaJson */

$galleryImages = ProductPageViewHelper::buildGalleryImages($product);
$mainImage = ProductPageViewHelper::getMainImage($galleryImages);
$thumbImages = ProductPageViewHelper::getThumbnailImages($galleryImages);

$price = ProductPageViewHelper::formatPrice($product);
$alt = ProductPageViewHelper::buildImageAlt($product, $category);
$sku = ProductPageViewHelper::getSku($product);
$quantity = ProductPageViewHelper::getQuantity($product);
$accordionItems = ProductPageViewHelper::buildAccordionItems($product);

echo $this->render('_schema', [
    'schemaJson' => $schemaJson,
    'breadcrumbSchemaJson' => $breadcrumbSchemaJson,
]);
?>

<article class="product-page product-page--design">
    <?= $this->render('_breadcrumbs', ['breadcrumbs' => $breadcrumbs]) ?>

    <section class="product-design">
        <div class="product-design__media js-product-gallery">
            <?php if ($mainImage !== null): ?>
                <div class="product-design__thumbs" aria-label="<?= Html::encode(Yii::t('frontend', 'Product images')) ?>">
                    <?php foreach ($thumbImages as $index => $imageUrl): ?>
                        <?php $imageNumber = $index + 2; ?>

                        <button
                            class="product-design__thumb-button js-product-gallery-thumb"
                            type="button"
                            data-src="<?= Html::encode($imageUrl) ?>"
                            data-alt="<?= Html::encode($alt . ' #' . $imageNumber) ?>"
                            aria-label="<?= Html::encode(Yii::t('frontend', 'Show product image {number}', ['number' => $imageNumber])) ?>"
                        >
                            <img
                                class="product-design__thumb-image"
                                src="<?= Html::encode($imageUrl) ?>"
                                alt="<?= Html::encode($alt . ' #' . $imageNumber) ?>"
                                loading="lazy"
                            >
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="product-design__main-media">
                    <img
                        class="product-design__main-image js-product-gallery-main"
                        src="<?= Html::encode($mainImage) ?>"
                        alt="<?= Html::encode($alt) ?>"
                        width="720"
                        height="720"
                        loading="eager"
                    >
                </div>
            <?php else: ?>
                <div class="product-design__main-media product-design__main-media--empty">
                    <div class="product-design__empty-image">
                        <?= Html::encode($product->name) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-design__summary">
            <h1 class="product-design__title">
                <?= Html::encode($product->name) ?>
            </h1>

            <div class="product-design__price">
                <?= Html::encode($price) ?>
            </div>

            <?php if ($sku !== ''): ?>
                <div class="product-design__sku">
                    <span class="product-design__meta-label">
                        <?= Html::encode(Yii::t('frontend', 'SKU')) ?>:
                    </span>
                    <span class="product-design__meta-value">
                        <?= Html::encode($sku) ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($product->getIsAvailable()): ?>
                <div class="product-design__actions">
                    <?= Html::beginForm(['/cart/add'], 'post', [
                        'class' => 'product-design__form js-catalog-add-to-cart',
                        'data-product-name' => $product->name,
                    ]) ?>
                    <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                    <?= Html::hiddenInput('qty', 1) ?>
                    <button class="product-design__button product-design__button--secondary" type="submit">
                        <?= Html::encode(Yii::t('frontend', 'Add to cart')) ?>
                    </button>
                    <?= Html::endForm() ?>

                    <?= Html::beginForm(['/cart/buy-now'], 'post', [
                        'class' => 'product-design__form product-design__form--buy-now',
                    ]) ?>
                    <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                    <?= Html::hiddenInput('qty', 1) ?>
                    <button class="product-design__button product-design__button--primary" type="submit">
                        <?= Html::encode(Yii::t('frontend', 'Buy now')) ?>
                    </button>
                    <?= Html::endForm() ?>
                </div>

                <div class="product-design__quantity">
                    <span class="product-design__meta-label">
                        <?= Html::encode(Yii::t('frontend', 'Quantity')) ?>:
                    </span>
                    <span class="product-design__meta-value">
                        <?= Html::encode((string)$quantity) ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="product-design__unavailable">
                    <?= Html::encode(Yii::t('frontend', 'Out of stock')) ?>
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

<?= $this->render('__cart-side-box') ?>
