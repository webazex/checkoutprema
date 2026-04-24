<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var frontend\models\CustomerRestoreRequestForm $model */

$this->title = 'Відновлення доступу';
?>

<div class="customer-restore">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        Вкажіть email, який ви використовували під час оформлення замовлення.
        Якщо акаунт існує, ми надішлемо інструкції для встановлення або скидання пароля.
    </p>

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success">
            <?= Html::encode(Yii::$app->session->getFlash('success')) ?>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin([
        'id' => 'customer-restore-form',
    ]); ?>

    <?= $form->field($model, 'email')->textInput([
        'autofocus' => true,
        'autocomplete' => 'email',
    ]) ?>

    <div class="form-group">
        <?= Html::submitButton('Надіслати посилання', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>