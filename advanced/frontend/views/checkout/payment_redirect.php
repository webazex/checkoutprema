<?php

/** @var yii\web\View $this */
/** @var array $order */
/** @var array $payment */
/** @var array $nextAction */

use yii\helpers\Html;

$this->title = 'Redirecting to payment';

$renderFields = static function (string $name, mixed $value) use (&$renderFields): string {
    if (is_array($value)) {
        $html = '';
        foreach ($value as $item) {
            $html .= $renderFields($name . '[]', $item);
        }
        return $html;
    }

    return Html::hiddenInput($name, (string)$value);
};
?>

<h1>Redirecting to payment…</h1>
<p>Order: <?= Html::encode($order['hash']) ?></p>

<form id="payment-redirect-form" method="<?= Html::encode($nextAction['method']) ?>" action="<?= Html::encode($nextAction['url']) ?>">
    <?php foreach ($nextAction['payload'] as $name => $value): ?>
        <?= $renderFields($name, $value) ?>
    <?php endforeach; ?>
</form>

<script>
    document.getElementById('payment-redirect-form').submit();
</script>