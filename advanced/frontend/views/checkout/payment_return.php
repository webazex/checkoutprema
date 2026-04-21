<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $query */
/** @var array $post */
/** @var string $method */

$this->title = 'Payment return';
$dirtyOrderId = $query['orderReference'];
$amount = $query['amount'];
$email = $query['email'];
$phone = $query['phone'];
$transactionStatus = $query['transactionStatus'];
$msgToClient = ($transactionStatus == 'Declined') ? '' : 'failure';
?>
<div class="payment-return">
    <h1><?= Yii::t('frontend', 'status_message'); ?></h1>
    <a href="https://www.premabrand.com.ua/" class="payment-return__link link-to-front">
        <span><?= Yii::t('frontend', 'link_to_front'); ?></span>
    </a>
    <a href="https://checkoutprema.biz.ua/customer/" class="payment-return__link link-to-pa">
        <span><?= Yii::t('frontend', 'link_to_pa'); ?></span>
    </a>
</div>