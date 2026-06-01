<?php

use frontend\services\cart\ActiveCartProvider;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var array|null $cartState */

$t = static fn(string $message, array $params = []): string => Yii::t('frontend', $message, $params);

$cartState = $cartState ?? (new ActiveCartProvider())->getState();
$cartItemsCount = max(0, (int)($cartState['itemsCount'] ?? 0));
$hasItems = (bool)($cartState['hasItems'] ?? $cartItemsCount > 0);
$cartUrl = (string)($cartState['cartUrl'] ?? Url::to(['/cart/index']));

$emptyText = $t('Cart is empty');
$activeText = $t('Place order');
?>

<div class="cart-side-box<?= $hasItems ? ' cart-side-box--active' : ' cart-side-box--empty' ?>" id="cart-side-box">
    <a
            href="<?= Html::encode($cartUrl) ?>"
            class="cart-side-box__inner js-cart-side-action<?= $hasItems ? '' : ' is-disabled' ?>"
            aria-disabled="<?= $hasItems ? 'false' : 'true' ?>"
            tabindex="<?= $hasItems ? '0' : '-1' ?>"
            data-empty-text="<?= Html::encode($emptyText) ?>"
            data-active-text="<?= Html::encode($activeText) ?>"
    >
        <span class="cart-side-box__header-block">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M6.5 8H17.5L18.5 21H5.5L6.5 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                <path d="M9 8V6C9 4.34315 10.3431 3 12 3C13.6569 3 15 4.34315 15 6V8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
            </svg>
            <span class="header-block__counter js-cart-count" data-cart-count><?= $cartItemsCount ?></span>
        </span>

        <span class="card-side-box__btn checkout-btn order-form__btn">
            <span class="js-cart-side-text">
                <?= Html::encode($hasItems ? $activeText : $emptyText) ?>
            </span>
        </span>
    </a>
</div>