<?php

/** @var \yii\web\View $this */
/** @var string $content */
use frontend\assets\AppAsset;


AppAsset::register($this);
$this->beginPage(); 
echo $this->render('header');?>
<main>
    <div class="site-size"> 
        <?= $content ?>
    </div>
</main>

<?php
echo $this->render('footer');
$this->endPage();
?>
