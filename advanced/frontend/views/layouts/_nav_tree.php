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
            $hasChildren ? 'js-mobile-parent-toggle' : '',
            !empty($item['isActive']) ? 'menu__link--active' : '',
    ];

    $itemUrl = (string)($item['url'] ?? '#');
    $itemLabel = (string)($item['label'] ?? '');
    ?>

    <div class="<?= Html::encode(trim(implode(' ', array_filter($itemClasses)))) ?>">
        <a
                href="<?= Html::encode($itemUrl) ?>"
                class="<?= Html::encode(trim(implode(' ', array_filter($linkClasses)))) ?>"
                <?php if ($hasChildren): ?>
                    aria-expanded="false"
                <?php endif; ?>
                <?php if (!empty($item['target'])): ?>
                    target="<?= Html::encode((string)$item['target']) ?>"
                <?php endif; ?>
                <?php if (!empty($item['rel'])): ?>
                    rel="<?= Html::encode((string)$item['rel']) ?>"
                <?php endif; ?>
        >
            <span class="menu__link-text">
                <?= Html::encode($itemLabel) ?>
            </span>

            <?php if ($hasChildren): ?>
                <span class="menu__triangle-box" aria-hidden="true">
                    <span></span>
                    <span></span>
                </span>
            <?php endif; ?>
        </a>

        <?php if ($hasChildren): ?>
            <div class="menu__submenu menu__submenu--level-<?= (int)$level ?>">
                <div class="menu__item menu__item--all menu__item--mobile-only">
                    <a href="<?= Html::encode($itemUrl) ?>" class="menu__link menu__link--all">
                        <span class="menu__link-text">
                            <?= Html::encode(Yii::t('frontend', 'All products')) ?>
                        </span>
                    </a>
                </div>

                <?= $this->render('_nav_tree', [
                        'items' => $children,
                        'level' => $level + 1,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>