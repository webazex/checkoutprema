<?php
use yii\helpers\Html;
use yii\helpers\Url;
$t = static fn(string $message, array $params = []): string => Yii::t('frontend', $message, $params);
?>
<div class="cart-side-box" id="cart-side-box">
    <div class="cart-side-box__header-block">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6.5 8H17.5L18.5 21H5.5L6.5 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
            <path d="M9 8V6C9 4.34315 10.3431 3 12 3C13.6569 3 15 4.34315 15 6V8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
        </svg>
        <span class="header-block__counter">0</span>
    </div>
    <button type="button" class="card-side-box__btn checkout-btn order-form__btn">
        <span>
           <?= Html::encode($t('Place order')) ?>
        </span>
    </button>
</div>
