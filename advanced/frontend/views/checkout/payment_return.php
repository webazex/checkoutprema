<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array $context
 */

$t = static fn(string $message, array $params = []): string => Yii::t('frontend', $message, $params);

$type = (string)($context['type'] ?? 'generic');
$title = (string)($context['title'] ?? $t('Payment return'));
$message = (string)($context['message'] ?? '');
$note = $context['note'] ?? null;
$orderId = $context['orderId'] ?? null;
$paymentStatus = $context['paymentStatus'] ?? null;

$this->title = $title;

?>

<section class="page-container payment-return-page payment-return-page--<?= Html::encode($type) ?>">
    <div class="payment-return-card">
        <h1 class="title__txt-h2 payment-return-card__title">
            <?= Html::encode($title) ?>
        </h1>

        <?php if ($message !== ''): ?>
            <p class="payment-return-card__text">
                <?= Html::encode($message) ?>
            </p>
        <?php endif; ?>

        <?php if ($orderId): ?>
            <p class="payment-return-card__meta">
                <?= Html::encode($t('Order')) ?> #<?= Html::encode((string)$orderId) ?>
            </p>
        <?php endif; ?>

        <?php if ($paymentStatus): ?>
            <p class="payment-return-card__meta">
                <?= Html::encode($t('Payment status')) ?>:
                <?= Html::encode((string)$paymentStatus) ?>
            </p>
        <?php endif; ?>

        <?php if (is_string($note) && trim($note) !== ''): ?>
            <p class="payment-return-card__note">
                <?= Html::encode($note) ?>
            </p>
        <?php endif; ?>

        <div class="payment-return-card__actions">
            <a class="order-form__btn payment-return-card__btn"
               href="<?= Html::encode(Url::to(['/catalog'])) ?>">
                <?= Html::encode($t('Back to store')) ?>
            </a>
        </div>
    </div>
</section>