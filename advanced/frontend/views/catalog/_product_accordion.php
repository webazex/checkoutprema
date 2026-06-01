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
        $title = (string)($item['title'] ?? '');
        ?>

        <details class="product-accordion__item" <?= $isOpen ? 'open' : '' ?>>
            <summary class="product-accordion__summary">
                <span><?= Html::encode($title) ?></span>
                <span class="product-accordion__icon" aria-hidden="true"></span>
            </summary>

            <div class="product-accordion__body">
                <?php if ($type === 'table'): ?>
                    <?php $rows = $item['rows'] ?? []; ?>

                    <?php if ($rows !== []): ?>
                        <table class="product-accordion__table">
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <th><?= Html::encode((string)$row['label']) ?></th>
                                    <td><?= Html::encode((string)$row['value']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="product-accordion__empty">
                            <?= Html::encode(Yii::t('frontend', 'No product characteristics are available yet.')) ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="product-accordion__content">
                        <?= (string)($item['content'] ?? '') ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>
</div>