<?php

use yii\helpers\Html;

/**
 * @var array<int, array{
 *     title: string,
 *     type?: string,
 *     content?: string,
 *     rows?: array<int, array{label: string, value: string}>,
 *     open?: bool
 * }> $items
 */

if (empty($items)) {
    return;
}
?>

<div class="product-accordion">
    <?php foreach ($items as $index => $item): ?>
        <?php
        $type = (string)($item['type'] ?? 'html');
        $isOpen = (bool)($item['open'] ?? $index === 0);
        ?>

        <details class="product-accordion__item" <?= $isOpen ? 'open' : '' ?>>
            <summary class="product-accordion__summary">
                <span><?= Html::encode((string)($item['title'] ?? '')) ?></span>
                <span class="product-accordion__icon" aria-hidden="true"></span>
            </summary>

            <div class="product-accordion__body">
                <?php if ($type === 'table'): ?>
                    <?= $this->render('_product_accordion_table', [
                            'rows' => $item['rows'] ?? [],
                    ]) ?>
                <?php else: ?>
                    <div class="product-accordion__content">
                        <?= (string)($item['content'] ?? '') ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>
</div>