<?php

declare(strict_types=1);

/**
 * @var array $cart
 * @var string $hash
 * @var frontend\models\CheckoutForm|null $model
 */

use frontend\assets\CheckoutAsset;
use frontend\helpers\checkout\CheckoutPageViewHelper;
use frontend\models\CheckoutForm;
use yii\helpers\Html;

$model ??= new CheckoutForm();

$page = new CheckoutPageViewHelper($cart, $hash, $model);
$field = $page->field();

$this->title = $page->t('Checkout');

CheckoutAsset::register($this);

?>

<?= $page->flash('error') ?>
<?= $page->flash('success') ?>

<?php if (!$page->hasItems()): ?>
    <section class="empty-cart">
        <h1 class="empty-cart__title title__txt-h2">
            <?= Html::encode($page->t('Cart')) ?>
        </h1>

        <div class="empty-cart__centered-box">
            <p><?= Html::encode($page->t('Your cart is empty :(')) ?></p>

            <a class="centered-box__link" href="<?= Html::encode($page->catalogUrl()) ?>">
                <span class="link__txt"><?= Html::encode($page->t('Go to catalog')) ?></span>
            </a>
        </div>
    </section>
<?php else: ?>
    <section class="page-container checkout-page">
        <div class="page-container__customer-box checkout-page__customer">
            <div class="customer-box__title">
                <h1 class="title__txt-h2">
                    <?= Html::encode($page->t('Checkout')) ?>
                </h1>
            </div>

            <form class="customer-box__order-form order-form"
                  method="post"
                  action="<?= Html::encode($page->submitUrl()) ?>"
                  novalidate>

                <?= $page->csrfInput() ?>

                <div class="order-form__title">
                    <h2 class="title__txt-h3">
                        <?= Html::encode($page->t('Recipient details')) ?>
                    </h2>
                </div>

                <?= $field->textInput('first_name', 'First name', [
                        'id' => 'checkout-first-name',
                        'autocomplete' => 'given-name',
                ]) ?>

                <?= $field->textInput('last_name', 'Last name', [
                        'id' => 'checkout-last-name',
                        'autocomplete' => 'family-name',
                ]) ?>

                <?= $field->telInput('phone', 'Phone number', [
                        'id' => 'checkout-phone',
                ]) ?>

                <?= $field->emailInput('email', 'Email', [
                        'id' => 'checkout-email',
                ]) ?>

                <div class="order-form__title checkout-section-title">
                    <h2 class="title__txt-h3">
                        <?= Html::encode($page->t('Delivery')) ?>
                    </h2>
                </div>

                <?= $field->textInput('region', 'Select region', [
                        'id' => 'region',
                        'autocomplete' => 'address-level1',
                ]) ?>

                <?= $field->textInput('city', 'Select city', [
                        'id' => 'city',
                        'autocomplete' => 'address-level2',
                ]) ?>

                <?= $field->textInput('branch', 'Specify Nova Poshta branch number', [
                        'id' => 'checkout-branch',
                        'inputmode' => 'numeric',
                        'autocomplete' => 'off',
                ]) ?>

                <div class="order-form__title checkout-section-title">
                    <h2 class="title__txt-h3">
                        <?= Html::encode($page->t('Payment')) ?>
                    </h2>
                </div>

                <?= $field->hiddenInput('payment_method', 'wayforpay') ?>

                <button class="order-form__btn" type="submit">
                    <?= Html::encode($page->t('Place order')) ?>
                </button>
            </form>
        </div>

        <aside class="page-container__cart-box checkout-page__cart">
            <div class="cart-box__title checkout-cart-title">
                <h2 class="title__txt-h2">
                    <?= Html::encode($page->t('In cart')) ?>
                </h2>

                <?= $page->clearCartForm() ?>
            </div>

            <div class="cart-box__products checkout-products">
                <?= $page->renderItems() ?>
            </div>

            <div class="cart-box__price-row checkout-total">
                <span class="price-row__label"><?= Html::encode($page->t('Total')) ?></span>
                <span class="checkout-total__value">
                    <?= Html::encode($page->total()) ?>
                </span>
            </div>
        </aside>
    </section>

    <?= $page->confirmModal() ?>
<?php endif; ?>