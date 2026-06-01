<?php

use yii\helpers\Html;

/**
 * @var array<int, array{label: string, value: string}> $rows
 */

if (empty($rows)): ?>
    <p class="product-accordion__empty">
        <?= Html::encode(Yii::t('frontend', 'No product characteristics are available yet.')) ?>
    </p>
    <?php return; ?>
<?php endif; ?>

<table class="product-accordion__table">
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <th><?= Html::encode((string)($row['label'] ?? '')) ?></th>
            <td><?= Html::encode((string)($row['value'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>