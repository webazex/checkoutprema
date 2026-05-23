<?php

use yii\helpers\Html;

/** @var array<int, array{label: string, url: string}> $breadcrumbs */
?>

<nav class="catalog-breadcrumbs" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbs as $index => $item): ?>
        <?php if ($index > 0): ?>
            <span class="catalog-breadcrumbs__separator">→</span>
        <?php endif; ?>

        <?php if ($index === array_key_last($breadcrumbs)): ?>
            <span class="catalog-breadcrumbs__current"><?= Html::encode($item['label']) ?></span>
        <?php else: ?>
            <a href="<?= Html::encode($item['url']) ?>"><?= Html::encode($item['label']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>