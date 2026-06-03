<?php

use frontend\services\navigation\NavigationProvider;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array|null $footerTree */

$footerTree = $footerTree ?? Yii::$container
        ->get(NavigationProvider::class)
        ->getFooterTree();

$linkOptions = static function (array $item, array $options = []): array {
    $options['class'] = trim(($options['class'] ?? '') . ' item__menu-link');

    if (!empty($item['target']) && is_string($item['target'])) {
        $options['target'] = $item['target'];
    }

    if (!empty($item['rel']) && is_string($item['rel'])) {
        $options['rel'] = $item['rel'];
    }

    return $options;
};
?>

<footer class="site-footer">
    <div class="site-size footer-bg footer-paddings">
        <div class="site-size__footer-container">
            <div class="footer-container__footer">
                <div class="footer__logo-block">
                    <?= Html::a('Prēma', ['/site/index'], [
                            'class' => 'footer__logo-link',
                            'aria-label' => 'Prema',
                    ]) ?>
                </div>

                <?php foreach (array_values($footerTree) as $columnIndex => $column): ?>
                    <?php
                    $children = $column['children'] ?? [];

                    if (empty($children)) {
                        continue;
                    }
                    ?>

                    <div class="footer__menu-column footer__menu-column--<?= (int)$columnIndex + 1 ?>">
                        <div class="menu-column__title">
                            <span class="title__txt">
                                <?= Html::encode($column['label'] ?? '') ?>
                            </span>
                        </div>

                        <nav class="menu-column__list" aria-label="<?= Html::encode($column['label'] ?? '') ?>">
                            <?php foreach ($children as $item): ?>
                                <div class="menu-column__item">
                                    <?= Html::a(
                                            Html::tag('span', Html::encode($item['label'] ?? '')),
                                            $item['url'] ?? '#',
                                            $linkOptions($item)
                                    ) ?>
                                </div>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</footer>

<?php $this->endBody(); ?>
</body>
</html>