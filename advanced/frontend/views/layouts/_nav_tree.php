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
    ?>

    <div class="menu__item <?= $hasChildren ? 'menu__item--parent' : '' ?> <?= !empty($item['isActive']) ? 'menu__item--active' : '' ?>">
        <a
            href="<?= Html::encode((string)$item['url']) ?>"
            class="menu__link <?= !empty($item['isActive']) ? 'menu__link--active' : '' ?>"
            <?php if (!empty($item['target'])): ?>
                target="<?= Html::encode((string)$item['target']) ?>"
            <?php endif; ?>
            <?php if (!empty($item['rel'])): ?>
                rel="<?= Html::encode((string)$item['rel']) ?>"
            <?php endif; ?>
        >
            <span class="menu__link-text">
                <?= Html::encode((string)$item['label']) ?>
            </span>

            <?php if ($hasChildren): ?>
                <div class="menu__triangle-box" aria-hidden="true">
                    <span></span>
                    <span></span>
                </div>
            <?php endif; ?>
        </a>

        <?php if ($hasChildren): ?>
            <div class="menu__submenu menu__submenu--level-<?= (int)$level ?>">
                <?= $this->render('_nav_tree', [
                    'items' => $children,
                    'level' => $level + 1,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>