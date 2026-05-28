<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array $query
 * @var array $post
 * @var string $method
 */

$t = static fn(string $message, array $params = []): string => Yii::t('frontend', $message, $params);

$this->title = $t('Payment return');

?>

<section class="page-container payment-return-page">
    <div class="payment-return-card">
        <h1 class="title__txt-h2 payment-return-card__title">
            <?= Html::encode($t('Payment is being processed')) ?>
        </h1>

        <p class="payment-return-card__text">
            <?= Html::encode($t('Thank you. Your payment has been accepted for processing.')) ?>
        </p>

        <p class="payment-return-card__text">
            <?= Html::encode($t('Payment confirmation may take a few minutes. After confirmation, your order will be transferred to the manager.')) ?>
        </p>

        <p class="payment-return-card__note">
            <?= Html::encode($t('If you do not receive confirmation or the manager does not contact you, please contact us.')) ?>
        </p>

        <div class="payment-return-card__actions">
            <a class="order-form__btn payment-return-card__btn"
               href="<?= Html::encode(Url::to(['/catalog'])) ?>">
                <?= Html::encode($t('Back to store')) ?>
            </a>
        </div>
    </div>
</section>