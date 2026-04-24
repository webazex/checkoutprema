<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $joke */
/** @var common\models\customer\CustomerModel $customer */

$this->title = 'Відновлення доступу';
?>

<div class="customer-restore-authenticated">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-info">
        <?= Html::encode($joke) ?>
    </div>

    <p>
        Ви вже авторизовані як <?= Html::encode($customer->email) ?>.
    </p>

    <p>
        <?= Html::a('Перейти в кабінет', ['/customer/view', 'hash' => $customer->hash], ['class' => 'btn btn-primary']) ?>
    </p>
</div>