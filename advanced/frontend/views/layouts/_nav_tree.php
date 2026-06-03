<?php

use yii\bootstrap5\Html;

/**
 * @var yii\web\View $this
 * @var array $items
 * @var int $level
 */

$level ??= 0;
?>

<?php foreach ($items as $item): ?>
    <?php
    $children = $item['children'] ?? [];
    $hasChildren = !empty($children);

    $itemClasses = [
            'menu__item',
            $hasChildren ? 'menu__item--parent' : '',
            !empty($item['isActive']) ? 'menu__item--active' : '',
    ];

    $linkClasses = [
            'menu__link',
            !empty($item['isActive']) ? 'menu__link--active' : '',
    ];
    ?>

    <div class="<?= Html::encode(trim(implode(' ', array_filter($itemClasses)))) ?>">
        <a
                href="<?= Html::encode((string)($item['url'] ?? '#')) ?>"
                class="<?= Html::encode(trim(implode(' ', array_filter($linkClasses)))) ?>"
                <?php if (!empty($item['target'])): ?>
                    target="<?= Html::encode((string)$item['target']) ?>"
                <?php endif; ?>
                <?php if (!empty($item['rel'])): ?>
                    rel="<?= Html::encode((string)$item['rel']) ?>"
                <?php endif; ?>
        >
            <span class="menu__link-text">
                <?= Html::encode((string)($item['label'] ?? '')) ?>
            </span>

            <?php if ($hasChildren): ?>
                <span class="menu__triangle-box" aria-hidden="true">
                    <span></span>
                    <span></span>
                </span>
            <?php endif; ?>
        </a>

        <?php if ($hasChildren): ?>
            <button
                    class="menu__submenu-toggle js-mobile-submenu-toggle"
                    type="button"
                    aria-expanded="false"
                    aria-label="<?= Html::encode(Yii::t('frontend', 'Toggle submenu') . ': ' . (string)($item['label'] ?? '')) ?>"
            >
                <span class="menu__submenu-toggle-icon" aria-hidden="true"></span>
            </button>

            <div class="menu__submenu menu__submenu--level-<?= (int)$level ?>">
                <?= $this->render('_nav_tree', [
                        'items' => $children,
                        'level' => $level + 1,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>