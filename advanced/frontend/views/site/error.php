<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\HttpException;

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception|Throwable $exception */

$statusCode = 500;

if ($exception instanceof HttpException) {
    $statusCode = (int)$exception->statusCode;
}

$isNotFound = $statusCode === 404;

$pageTitle = $isNotFound
        ? Yii::t('frontend', 'Page not found')
        : Yii::t('frontend', 'Something went wrong');

$this->title = $pageTitle;

$headline = $isNotFound
        ? Yii::t('frontend', 'This page does not exist')
        : Yii::t('frontend', 'Temporary technical issue');

$description = $isNotFound
        ? Yii::t('frontend', 'The page may have been moved, deleted, or the address may contain a mistake.')
        : Yii::t('frontend', 'We are already working on it. Please try again later or return to the catalog.');

$codeLabel = '#' . $statusCode;
?>

<section class="error-page">
    <div class="site-size">
        <div class="error-page__card">
            <div class="error-page__content">
                <div class="error-page__eyebrow">
                    <?= Html::encode(Yii::t('frontend', 'Error')) ?>
                    <span><?= Html::encode($codeLabel) ?></span>
                </div>

                <h1 class="error-page__title">
                    <?= Html::encode($statusCode) ?>
                </h1>

                <h2 class="error-page__headline">
                    <?= Html::encode($headline) ?>
                </h2>

                <p class="error-page__text">
                    <?= Html::encode($description) ?>
                </p>

                <div class="error-page__actions">
                    <?= Html::a(
                            Html::encode(Yii::t('frontend', 'Go to homepage')),
                            Url::home(),
                            ['class' => 'error-page__btn error-page__btn--primary']
                    ) ?>

                    <?= Html::a(
                            Html::encode(Yii::t('frontend', 'Open catalog')),
                            ['/catalog/index'],
                            ['class' => 'error-page__btn error-page__btn--secondary']
                    ) ?>
                </div>
            </div>

            <div class="error-page__visual" aria-hidden="true">
                <div class="error-page__bubble error-page__bubble--large"></div>
                <div class="error-page__bubble error-page__bubble--medium"></div>
                <div class="error-page__bubble error-page__bubble--small"></div>

                <div class="error-page__number">
                    <?= Html::encode($statusCode) ?>
                </div>
            </div>
        </div>
    </div>
</section>