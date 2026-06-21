<?php

/** @var View $this */

/** @var string $content */

use frontend\assets\AppAsset;
use frontend\services\navigation\NavigationProvider;
use frontend\services\cart\ActiveCartProvider;
use yii\web\View;

AppAsset::register($this);
$navigationProvider = new NavigationProvider();
$this->beginPage();
$cartState = (new ActiveCartProvider())->getState();
echo $this->render('header',
        [
                'headerTree' => $navigationProvider->getHeaderTree(),
                'cartState' => $cartState,
        ]
); ?>
<main>
    <div class="site-size">
        <?= $content ?>
    </div>
</main>

<?php
echo $this->render('footer',
        [
                'footerTree' => $navigationProvider->getFooterTree(),
        ]
);
$this->endPage();
?>
