<?php

use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array|null $headerTree */
/** @var array|null $cartState */

$headerTree = $headerTree ?? [];
$cartState = $cartState ?? [];

$cartItemsCount = max(0, (int)($cartState['itemsCount'] ?? 0));
$cartUrl = (string)($cartState['cartUrl'] ?? Url::to(['/cart/index']));
$logoUrl = Url::to('@web/img/logo2.svg');
?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
    <meta charset="<?= Html::encode(Yii::$app->charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@200..900&display=swap" rel="stylesheet">

    <?php $this->registerCsrfMetaTags() ?>

    <title><?= Html::encode($this->title) ?></title>

    <?php $this->head() ?>
</head>
<body>
<header>
    <div class="site-size">
        <div class="header-container">
            <div class="header-container__header">
                <div class="header__logo">
                    <?= Html::a(
                            Html::img($logoUrl, [
                                    'class' => 'header__logo-img',
                                    'alt' => 'Prema',
                                    'loading' => 'eager',
                            ]),
                            Url::home(),
                            [
                                    'class' => 'header__logo-link',
                                    'aria-label' => 'Prema',
                            ]
                    ) ?>
                </div>

                <button
                        class="header__burger js-mobile-menu-toggle"
                        type="button"
                        aria-controls="site-mobile-menu"
                        aria-expanded="false"
                        aria-label="<?= Html::encode(Yii::t('frontend', 'Open menu')) ?>"
                        data-open-label="<?= Html::encode(Yii::t('frontend', 'Open menu')) ?>"
                        data-close-label="<?= Html::encode(Yii::t('frontend', 'Close menu')) ?>"
                >
                    <span class="header__burger-line"></span>
                    <span class="header__burger-line"></span>
                    <span class="header__burger-line"></span>
                </button>

                <nav
                        id="site-mobile-menu"
                        class="header__menu"
                        aria-label="<?= Html::encode(Yii::t('frontend', 'Main menu')) ?>"
                >
                    <?= $this->render('_nav_tree', [
                            'items' => $headerTree,
                            'level' => 0,
                    ]) ?>
                </nav>

                <div class="header__btns">
                    <a
                            href="<?= Html::encode(Url::to(['/customer/index'])) ?>"
                            class="btns__profile"
                            aria-label="<?= Html::encode(Yii::t('frontend', 'Customer account')) ?>"
                    >
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                            <path
                                    d="M5 21C5 17.134 8.13401 14 12 14C15.866 14 19 17.134 19 21M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                            />
                        </svg>
                    </a>

                    <a
                            href="<?= Html::encode($cartUrl) ?>"
                            class="btns__cart js-cart-link"
                            aria-label="<?= Html::encode(Yii::t('frontend', 'Cart')) ?>"
                    >
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                            <path
                                    d="M6.5 8H17.5L18.5 21H5.5L6.5 8Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linejoin="round"
                            />
                            <path
                                    d="M9 8V6C9 4.34315 10.3431 3 12 3C13.6569 3 15 4.34315 15 6V8"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                            />
                        </svg>

                        <span class="cart__count js-cart-count" data-cart-count>
                            <?= $cartItemsCount ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
