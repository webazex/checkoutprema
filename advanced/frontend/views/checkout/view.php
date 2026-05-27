<?php

/**
 * @var array $cart
 * @var string $hash
 */

use yii\helpers\Html;
use yii\helpers\Url;

$t = static fn(string $message, array $params = []): string => Yii::t('frontend', $message, $params);

$this->title = $t('Checkout');

$hasItems = !empty($cart['items']);
$items = $cart['items'] ?? [];
$currency = (string)($cart['currency'] ?? 'UAH');
$totalAmount = (string)($cart['totalAmount'] ?? '0');

$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();

$formatMoney = static function (mixed $amount, ?string $itemCurrency = null) use ($currency, $t): string {
    $value = is_numeric($amount) ? (float)$amount : 0.0;
    $formatted = fmod($value, 1.0) === 0.0
            ? number_format($value, 0, '.', ' ')
            : number_format($value, 2, '.', ' ');

    $currentCurrency = $itemCurrency ?: $currency;

    return $formatted . ' ' . ($currentCurrency === 'UAH' ? $t('UAH') : $currentCurrency);
};

?>

<?php if (Yii::$app->session->hasFlash('error')): ?>
    <div class="checkout-alert checkout-alert--error">
        <?= Html::encode(Yii::$app->session->getFlash('error')) ?>
    </div>
<?php endif; ?>

<?php if (Yii::$app->session->hasFlash('success')): ?>
    <div class="checkout-alert checkout-alert--success">
        <?= Html::encode(Yii::$app->session->getFlash('success')) ?>
    </div>
<?php endif; ?>

<?php if (!$hasItems): ?>
    <section class="empty-cart">
        <h1 class="empty-cart__title title__txt-h2">
            <?= Html::encode($t('Cart')) ?>
        </h1>

        <div class="empty-cart__centered-box">
            <p><?= Html::encode($t('Your cart is empty :(')) ?></p>

            <a class="centered-box__link" href="<?= Html::encode(Url::to(['/catalog'])) ?>">
                <span class="link__txt"><?= Html::encode($t('Go to catalog')) ?></span>
            </a>
        </div>
    </section>
<?php else: ?>
    <section class="page-container checkout-page">
        <div class="page-container__customer-box checkout-page__customer">
            <div class="customer-box__title">
                <h1 class="title__txt-h2">
                    <?= Html::encode($t('Checkout')) ?>
                </h1>
            </div>

            <form class="customer-box__order-form order-form"
                  method="post"
                  action="<?= Html::encode(Url::to(['checkout/submit', 'hash' => $hash])) ?>">

                <input type="hidden"
                       name="<?= Html::encode($csrfParam) ?>"
                       value="<?= Html::encode($csrfToken) ?>">

                <div class="order-form__title">
                    <h2 class="title__txt-h3">
                        <?= Html::encode($t('Recipient details')) ?>
                    </h2>
                </div>

                <label class="order-form__label checkout-field" for="checkout-first-name">
                    <span><?= Html::encode($t('First name')) ?></span>
                    <input id="checkout-first-name"
                           type="text"
                           name="first_name"
                           autocomplete="given-name"
                           required>
                </label>

                <label class="order-form__label checkout-field" for="checkout-last-name">
                    <span><?= Html::encode($t('Last name')) ?></span>
                    <input id="checkout-last-name"
                           type="text"
                           name="last_name"
                           autocomplete="family-name"
                           required>
                </label>

                <label class="order-form__label checkout-field" for="checkout-phone">
                    <span><?= Html::encode($t('Phone number')) ?></span>
                    <input id="checkout-phone"
                           type="tel"
                           name="phone"
                           autocomplete="tel"
                           required>
                </label>

                <label class="order-form__label checkout-field" for="checkout-email">
                    <span><?= Html::encode($t('Email')) ?></span>
                    <input id="checkout-email"
                           type="email"
                           name="email"
                           autocomplete="email"
                           required>
                </label>

                <div class="order-form__title checkout-section-title">
                    <h2 class="title__txt-h3">
                        <?= Html::encode($t('Delivery')) ?>
                    </h2>
                </div>

                <label class="order-form__label checkout-field" for="region">
                    <span><?= Html::encode($t('Select region')) ?></span>
                    <input id="region"
                           type="text"
                           name="region"
                           autocomplete="address-level1">
                </label>

                <label class="order-form__label checkout-field" for="city">
                    <span><?= Html::encode($t('Select city')) ?></span>
                    <input id="city"
                           type="text"
                           name="city"
                           autocomplete="address-level2">
                </label>

                <label class="order-form__label checkout-field" for="branch">
                    <span><?= Html::encode($t('Select branch')) ?></span>
                    <input id="branch"
                           type="text"
                           name="branch"
                           autocomplete="street-address">
                </label>

                <div class="order-form__title checkout-section-title">
                    <h2 class="title__txt-h3">
                        <?= Html::encode($t('Payment')) ?>
                    </h2>
                </div>

                <input type="hidden" name="payment_method" value="wayforpay">

                <button class="order-form__btn" type="submit">
                    <?= Html::encode($t('Place order')) ?>
                </button>
            </form>
        </div>

        <aside class="page-container__cart-box checkout-page__cart">
            <div class="cart-box__title checkout-cart-title">
                <h2 class="title__txt-h2">
                    <?= Html::encode($t('In cart')) ?>
                </h2>

                <form method="post"
                      action="<?= Html::encode(Url::to(['checkout/clear', 'hash' => $hash])) ?>"
                      class="checkout-clear-form">
                    <input type="hidden"
                           name="<?= Html::encode($csrfParam) ?>"
                           value="<?= Html::encode($csrfToken) ?>">

                    <button type="submit"
                            class="checkout-clear-btn"
                            onclick="return confirm('<?= Html::encode($t('Are you sure you want to clear the cart?')) ?>');">
                        <?= Html::encode($t('Clear cart')) ?>
                    </button>
                </form>
            </div>

            <div class="cart-box__products checkout-products">
                <?php foreach ($items as $item): ?>
                    <?php
                    $itemId = (int)($item['id'] ?? 0);
                    $itemTitle = (string)($item['title'] ?? '');
                    $itemSku = (string)($item['sku'] ?? '');
                    $itemQty = (int)($item['quantity'] ?? 0);
                    $itemPrice = $item['price'] ?? 0;
                    $itemSubtotal = $item['subtotal'] ?? 0;
                    $itemCurrency = (string)($item['currency'] ?? $currency);
                    ?>

                    <article class="products__product-item checkout-product">
                        <div class="product-item__thumb checkout-product__thumb" aria-hidden="true">
                            <span class="checkout-product__thumb-text">
                                <?= Html::encode(mb_substr($itemTitle, 0, 1, 'UTF-8') ?: 'P') ?>
                            </span>
                        </div>

                        <div class="product-item__info-box checkout-product__info">
                            <div class="info-box__product-name checkout-product__name">
                                <?= Html::encode($itemTitle) ?>
                            </div>

                            <?php if ($itemSku !== ''): ?>
                                <div class="info-box__properties checkout-product__props">
                                    <div class="properties__prop-row">
                                        <span class="prop-row__key"><?= Html::encode($t('SKU')) ?>:</span>
                                        <span class="prop-row__val"><?= Html::encode($itemSku) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="checkout-product__meta">
                                <span><?= Html::encode($formatMoney($itemPrice, $itemCurrency)) ?></span>
                                <span>× <?= Html::encode((string)$itemQty) ?></span>
                            </div>
                        </div>

                        <div class="checkout-product__side">
                            <form method="post"
                                  action="<?= Html::encode(Url::to(['checkout/remove-item', 'hash' => $hash])) ?>"
                                  class="checkout-remove-form">
                                <input type="hidden"
                                       name="<?= Html::encode($csrfParam) ?>"
                                       value="<?= Html::encode($csrfToken) ?>">

                                <input type="hidden" name="itemId" value="<?= $itemId ?>">

                                <button type="submit"
                                        class="product-item__remove-icon checkout-remove-btn"
                                        aria-label="<?= Html::encode($t('Remove item')) ?>"
                                        onclick="return confirm('<?= Html::encode($t('Are you sure you want to remove this item?')) ?>');">
                                    ×
                                </button>
                            </form>

                            <div class="checkout-product__subtotal">
                                <?= Html::encode($formatMoney($itemSubtotal, $itemCurrency)) ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="cart-box__price-row checkout-total">
                <span class="price-row__label"><?= Html::encode($t('Total')) ?></span>
                <span class="checkout-total__value">
                    <?= Html::encode($formatMoney($totalAmount, $currency)) ?>
                </span>
            </div>
        </aside>
    </section>
<?php endif; ?>