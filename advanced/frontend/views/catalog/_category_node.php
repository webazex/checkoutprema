<?php

use common\models\catalog\CatalogCategoryModel;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array{category: CatalogCategoryModel, children: array, hasProducts: bool} $node
 * @var int $level
 */

$category = $node['category'];
$children = $node['children'];
$hasChildren = $children !== [];
$categoryUrl = Url::to(['/catalog/category', 'categorySlug' => $category->slug]);
$itemClasses = [
    'catalog-category-menu__item',
    'catalog-category-menu__item--level-' . $level,
];

if ($hasChildren) {
    $itemClasses[] = 'catalog-category-menu__item--has-children';
}

$labelContent = '';

if (!empty($category->thumbnail_url) && $level === 0) {
    $labelContent .= Html::img($category->thumbnail_url, [
        'class' => 'catalog-category-menu__image',
        'alt' => $category->name,
        'loading' => 'lazy',
        'width' => 360,
        'height' => 240,
    ]);
}

$labelContent .= Html::tag('span', Html::encode($category->name), [
    'class' => 'catalog-category-menu__label',
]);

if ($hasChildren) {
    $labelContent .= Html::tag('span', '▾', [
        'class' => 'catalog-category-menu__arrow',
        'aria-hidden' => 'true',
    ]);
}
?>

<li class="<?= Html::encode(implode(' ', $itemClasses)) ?>">
    <?php if ($hasChildren): ?>
        <button
            class="catalog-category-menu__trigger js-category-collapse-toggle"
            type="button"
            aria-expanded="false"
        >
            <?= $labelContent ?>
        </button>

        <ul class="catalog-category-menu__submenu">
            <?php foreach ($children as $childNode): ?>
                <?= $this->render('_category_node', [
                    'node' => $childNode,
                    'level' => $level + 1,
                ]) ?>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <a class="catalog-category-menu__link" href="<?= Html::encode($categoryUrl) ?>">
            <?= $labelContent ?>
        </a>
    <?php endif; ?>
</li>