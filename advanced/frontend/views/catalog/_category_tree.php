<?php

/** @var array<int, array{category: \common\models\catalog\CatalogCategoryModel, children: array, hasProducts: bool}> $categoryTree */
?>

<section class="catalog-categories" aria-label="Категорії товарів">
    <ul class="catalog-category-menu">
        <?php foreach ($categoryTree as $node): ?>
            <?= $this->render('_category_node', [
                'node' => $node,
                'level' => 0,
            ]) ?>
        <?php endforeach; ?>
    </ul>
</section>