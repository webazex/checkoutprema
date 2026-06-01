<?php

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use yii\helpers\Html;
use common\models\meta\MetaModel;

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

$meta = MetaModel::getEntityTextValues(MetaModel::ENTITY_PRODUCT, (int)$product->id, [
        'keycrm.custom.CT_1001',
        'keycrm.custom.ct_1001',
        'keycrm.custom.forma',
        'keycrm.custom.CT_1002',
        'keycrm.custom.ct_1002',
        'keycrm.custom.material',
        'keycrm.custom.material-tovaru',
]);

$pickMeta = static function (array $keys) use ($meta): ?string {
    foreach ($keys as $key) {
        $value = $meta[$key] ?? null;

        if ($value !== null && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }

    return null;
};

$characteristics = [];

$addCharacteristic = static function (string $label, mixed $value) use (&$characteristics): void {
    if ($value === null) {
        return;
    }

    $value = trim((string)$value);

    if ($value === '') {
        return;
    }

    $characteristics[] = [
            'label' => $label,
            'value' => $value,
    ];
};

$formatWeight = static function (mixed $value): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    $kg = (float)$value;

    if ($kg <= 0) {
        return null;
    }

    if ($kg < 1) {
        return (string)((int)round($kg * 1000)) . ' г';
    }

    $formatted = number_format($kg, 3, '.', ' ');
    $formatted = rtrim(rtrim($formatted, '0'), '.');

    return $formatted . ' кг';
};

$formatSize = static function (?int $length, ?int $width, ?int $height): ?string {
    $parts = [];

    foreach ([$length, $width, $height] as $value) {
        $value = (int)$value;

        if ($value <= 0) {
            continue;
        }

        $cm = $value / 10;
        $precision = $value % 10 === 0 ? 0 : 1;
        $parts[] = number_format($cm, $precision, '.', ' ');
    }

    if ($parts === []) {
        return null;
    }

    return implode(' × ', $parts) . ' см';
};

$formValue = $pickMeta([
        'keycrm.custom.CT_1001',
        'keycrm.custom.ct_1001',
        'keycrm.custom.forma',
]);

$materialValue = $pickMeta([
        'keycrm.custom.CT_1002',
        'keycrm.custom.ct_1002',
        'keycrm.custom.material',
        'keycrm.custom.material-tovaru',
]);

$addCharacteristic($t('SKU'), $product->sku);
$addCharacteristic($t('Barcode'), $product->barcode);
$addCharacteristic($t('Form'), $formValue);
$addCharacteristic($t('Material'), $materialValue);
$addCharacteristic($t('Weight'), $formatWeight($product->weight_kg));
$addCharacteristic($t('Size'), $formatSize(
        $product->length_mm !== null ? (int)$product->length_mm : null,
        $product->width_mm !== null ? (int)$product->width_mm : null,
        $product->height_mm !== null ? (int)$product->height_mm : null,
));

$descriptionHtml = !empty($product->description)
        ? (string)$product->description
        : '<p>' . Html::encode($t('Product description is being prepared.')) . '</p>';

$accordionItems = [
        [
                'title' => $t('Description'),
                'type' => 'html',
                'content' => $descriptionHtml,
                'open' => true,
        ],
        [
                'title' => $t('Characteristics'),
                'type' => 'table',
                'rows' => $characteristics,
                'open' => false,
        ],
];
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
                <div class="product-hero__actions">
                    <?= Html::beginForm(['/cart/add'], 'post', [
                            'class' => 'product-hero__form js-catalog-add-to-cart',
                            'data-product-name' => $product->name,
                    ]) ?>
                    <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                    <?= Html::hiddenInput('qty', 1) ?>
                    <button class="product-hero__button product-hero__button--secondary" type="submit">
                        Додати в кошик
                    </button>
                    <?= Html::endForm() ?>

                    <?= Html::beginForm(['/cart/buy-now'], 'post', [
                            'class' => 'product-hero__form product-hero__form--buy-now',
                    ]) ?>
                    <?= Html::hiddenInput('product_id', (int)$product->id) ?>
                    <?= Html::hiddenInput('qty', 1) ?>
                    <button class="product-hero__button product-hero__button--primary" type="submit">
                        Купити зараз
                    </button>
                    <?= Html::endForm() ?>
                </div>
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
<?=$this->render('__cart-side-box'); ?>