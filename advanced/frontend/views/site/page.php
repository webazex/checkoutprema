<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $slug */
/** @var array{title: string, h1: string, description: string, body: string} $page */

?>

<section class="site-page">
    <nav class="catalog-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= Html::encode(Url::to(['/site/index'])) ?>">Головна</a>
        <span class="catalog-breadcrumbs__separator">→</span>
        <span class="catalog-breadcrumbs__current"><?= Html::encode($page['h1']) ?></span>
    </nav>

    <header class="site-page__header">
        <h1><?= Html::encode($page['h1']) ?></h1>
        <p><?= Html::encode($page['description']) ?></p>
    </header>

    <div class="site-page__body">
        <p><?= Html::encode($page['body']) ?></p>
    </div>
</section>