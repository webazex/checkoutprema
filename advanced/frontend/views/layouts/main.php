<?php

/** @var \yii\web\View $this */
/** @var string $content */
use frontend\assets\AppAsset;
use frontend\services\navigation\NavigationProvider;

AppAsset::register($this);
$navigationProvider = new NavigationProvider();
$this->beginPage(); 
echo $this->render('header',
        [
                'headerTree' => $navigationProvider->getHeaderTree(),
        ]
);?>
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
