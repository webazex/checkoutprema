<?php

/** @var array $cart */
/** @var string $hash */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Checkout';

$hasItems = !empty($cart['items']);
$items = $cart['items'] ?? [];
?>

    <h1>Checkout</h1>

<?php if (Yii::$app->session->hasFlash('error')): ?>
    <div style="padding:10px;margin-bottom:16px;border:1px solid #dc3545;color:#842029;background:#f8d7da;">
        <?= Html::encode(Yii::$app->session->getFlash('error')) ?>
    </div>
<?php endif; ?>

<?php if (Yii::$app->session->hasFlash('success')): ?>
    <div style="padding:10px;margin-bottom:16px;border:1px solid #198754;color:#0f5132;background:#d1e7dd;">
        <?= Html::encode(Yii::$app->session->getFlash('success')) ?>
    </div>
<?php endif; ?>

    <h2>Your cart</h2>

<?php if (!$hasItems): ?>
    <p>Cart is empty.</p>
<?php else: ?>
    <div style="margin-bottom:16px;">
        <form method="post" action="<?= Url::to(['checkout/clear', 'hash' => $hash]) ?>" style="display:inline-block;">
            <input type="hidden" name="<?= Html::encode(Yii::$app->request->csrfParam) ?>" value="<?= Html::encode(Yii::$app->request->getCsrfToken()) ?>">
            <button type="submit" onclick="return confirm('Clear the whole cart?');">Clear cart</button>
        </form>
    </div>

    <table border="1" cellpadding="8" cellspacing="0" width="100%" style="margin-bottom:16px;border-collapse:collapse;">
        <thead>
        <tr>
            <th align="left">Title</th>
            <th align="left">SKU</th>
            <th align="right">Price</th>
            <th align="right">Qty</th>
            <th align="right">Subtotal</th>
            <th align="center">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= Html::encode((string)($item['title'] ?? '')) ?></td>
                <td><?= Html::encode((string)($item['sku'] ?? '')) ?></td>
                <td align="right">
                    <?= Html::encode((string)($item['price'] ?? '0')) ?>
                    <?= Html::encode((string)($item['currency'] ?? ($cart['currency'] ?? '')) ) ?>
                </td>
                <td align="right"><?= Html::encode((string)($item['quantity'] ?? '0')) ?></td>
                <td align="right">
                    <?= Html::encode((string)($item['subtotal'] ?? '0')) ?>
                    <?= Html::encode((string)($item['currency'] ?? ($cart['currency'] ?? '')) ) ?>
                </td>
                <td align="center">
                    <form method="post" action="<?= Url::to(['checkout/remove-item', 'hash' => $hash]) ?>" style="display:inline-block;">
                        <input type="hidden" name="<?= Html::encode(Yii::$app->request->csrfParam) ?>" value="<?= Html::encode(Yii::$app->request->getCsrfToken()) ?>">
                        <input type="hidden" name="itemId" value="<?= (int)($item['id'] ?? 0) ?>">
                        <button type="submit" onclick="return confirm('Remove this item?');">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <strong>Total:</strong>
        <?= Html::encode((string)($cart['totalAmount'] ?? '0')) ?>
        <?= Html::encode((string)($cart['currency'] ?? '')) ?>
    </p>
<?php endif; ?>

    <hr>

<?php if ($hasItems): ?>
    <form method="post" action="<?= Url::to(['checkout/submit', 'hash' => $hash]) ?>">
        <input type="hidden" name="<?= Html::encode(Yii::$app->request->csrfParam) ?>" value="<?= Html::encode(Yii::$app->request->getCsrfToken()) ?>">

        <div style="margin-bottom:12px;">
            <label>Email</label><br>
            <input type="email" name="email" required style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>Phone</label><br>
            <input type="text" name="phone" required style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>First name</label><br>
            <input type="text" name="first_name" required style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>Last name</label><br>
            <input type="text" name="last_name" required style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>Region</label><br>
            <input type="text" name="region" style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>City</label><br>
            <input type="text" name="city" style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>Branch</label><br>
            <input type="text" name="branch" style="width:100%;max-width:420px;">
        </div>

        <div style="margin-bottom:12px;">
            <label>Payment method</label><br>
            <select name="payment_method" style="width:100%;max-width:420px;">
                <option value="wayforpay">WayForPay</option>
            </select>
        </div>

        <button type="submit">Proceed to payment</button>
    </form>
<?php else: ?>
    <p>Checkout form is unavailable because the cart is empty.</p>
<?php endif; ?>