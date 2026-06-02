<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var ProductModel $product */

$category = $product->category;

$productUrl = $category instanceof CatalogCategoryModel && !empty($product->slug)
        ? Url::to([
                '/catalog/product',
                'categorySlug' => $category->slug,
                'productSlug' => $product->slug,
        ])
        : null;

$price = number_format((float)$product->price, 0, '.', ' ') . ' ' . Html::encode($product->currency ?: 'UAH');

$alt = trim($product->name . ($category instanceof CatalogCategoryModel ? ', ' . $category->name : ''));
?>

<article class="catalog-card">
    <div class="catalog-card__media">
        <?php if ($productUrl !== null): ?>
        <a class="catalog-card__image-link" href="<?= Html::encode($productUrl) ?>">
            <?php endif; ?>

            <?php if (!empty($product->thumbnail_url)): ?>
                <img
                        class="catalog-card__image"
                        src="<?= Html::encode($product->thumbnail_url) ?>"
                        alt="<?= Html::encode($alt) ?>"
                        loading="lazy"
                        width="420"
                        height="420"
                >
            <?php else: ?>
                <div class="catalog-card__image catalog-card__image--empty">
                    <?= Html::encode($product->name) ?>
                </div>
            <?php endif; ?>

            <?php if ($productUrl !== null): ?>
        </a>
    <?php endif; ?>
    </div>

    <div class="catalog-card__body">
        <h2 class="catalog-card__title">
            <?php if ($productUrl !== null): ?>
                <a href="<?= Html::encode($productUrl) ?>"><?= Html::encode($product->name) ?></a>
            <?php else: ?>
                <?= Html::encode($product->name) ?>
            <?php endif; ?>
        </h2>

        <div class="catalog-card__price"><?= $price ?></div>

        <?php if ($product->getIsAvailable()): ?>
            <div class="catalog-card__actions">
                <?= Html::beginForm(['/cart/add'], 'post', [
                        'class' => 'catalog-card__form js-catalog-add-to-cart',
                        'data-product-name' => $product->name,
                ]) ?>
                <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                <?= Html::hiddenInput('qty', 1) ?>
                <button class="catalog-card__button catalog-card__button--secondary" type="submit">
                    <?= Html::encode(Yii::t('frontend', 'To cart')) ?>
                </button>
                <?= Html::endForm() ?>

                <?= Html::beginForm(['/cart/buy-now'], 'post', [
                        'class' => 'catalog-card__form catalog-card__form--buy-now',
                ]) ?>
                <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                <?= Html::hiddenInput('qty', 1) ?>
                <button class="catalog-card__button catalog-card__button--primary" type="submit">
                    <?= Html::encode(Yii::t('frontend', 'Buy')) ?>
                </button>
                <?= Html::endForm() ?>
            </div>
        <?php else: ?>
            <div class="catalog-card__unavailable">
                <?= Html::encode(Yii::t('frontend', 'Product is out of stock.')) ?>
            </div>
        <?php endif; ?>
    </div>
</article>