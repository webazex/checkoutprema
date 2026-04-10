<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $cart */
/** @var string $hash */

$this->title = 'Checkout';
$hasItems = !empty($cart['items']);
?>

    <h1>Checkout</h1>

<?php if (Yii::$app->session->hasFlash('error')): ?>
    <div style="color: red; margin-bottom: 16px;">
        <?= Html::encode(Yii::$app->session->getFlash('error')) ?>
    </div>
<?php endif; ?>

    <h2>Your cart</h2>

<?php if (!$hasItems): ?>
    <p>Cart is empty.</p>
<?php else: ?>
    <table border="1" cellpadding="8" cellspacing="0" width="100%">
        <thead>
        <tr>
            <th>Title</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Subtotal</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cart['items'] as $item): ?>
            <tr>
                <td><?= Html::encode($item['title']) ?></td>
                <td><?= Html::encode((string)($item['sku'] ?? '')) ?></td>
                <td><?= Html::encode((string)$item['price']) ?> <?= Html::encode($item['currency']) ?></td>
                <td><?= Html::encode((string)$item['quantity']) ?></td>
                <td><?= Html::encode((string)$item['subtotal']) ?> <?= Html::encode($item['currency']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <strong>Total:</strong>
        <?= Html::encode((string)$cart['totalAmount']) ?> <?= Html::encode($cart['currency']) ?>
    </p>

    <hr>

    <form method="post" action="<?= Url::to(['checkout/submit', 'hash' => $hash]) ?>">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
        <?= Html::hiddenInput('cartHash', (string)($cart['hash'] ?? $hash)) ?>
        <?= Html::hiddenInput('sessionKey', (string)($cart['sessionKey'] ?? '')) ?>
        <?= Html::hiddenInput('sourceType', (string)($cart['sourceType'] ?? 'wix')) ?>

        <div>
            <label>Email</label><br>
            <input type="email" name="email" required>
        </div>

        <div>
            <label>Phone</label><br>
            <input type="text" name="phone" required>
        </div>

        <div>
            <label>First name</label><br>
            <input type="text" name="first_name" required>
        </div>

        <div>
            <label>Last name</label><br>
            <input type="text" name="last_name" required>
        </div>

        <div>
            <label>Region</label><br>
            <input type="text" name="region">
        </div>

        <div>
            <label>City</label><br>
            <input type="text" name="city">
        </div>

        <div>
            <label>Branch</label><br>
            <input type="text" name="branch">
        </div>

        <div>
            <label>Payment method</label><br>
            <input type="hidden" name="payment_method" value="wayforpay">
            <strong>WayForPay</strong>
        </div>

        <div style="margin-top: 16px;">
            <button type="submit">Proceed to payment</button>
        </div>
    </form>
<?php endif; ?>