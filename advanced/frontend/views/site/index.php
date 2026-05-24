<?php

use yii\helpers\Html;
use yii\helpers\Url;

?>

<section class="site-home">
    <div class="site-home__inner">
        <h1>Prema</h1>

        <p>
            Товари для йоги, пілатесу, спорту, ароматерапії та щоденних wellness-практик.
        </p>

        <a class="site-home__button" href="<?= Html::encode(Url::to(['/catalog/index'])) ?>">
            Перейти в каталог
        </a>
    </div>
</section>