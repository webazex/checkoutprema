<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var frontend\models\CustomerResetPasswordForm $model */

$this->title = 'Новий пароль';
?>

<div class="customer-reset-password">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        Встановіть новий пароль для входу до кабінету покупця.
    </p>

    <?php $form = ActiveForm::begin([
        'id' => 'customer-reset-password-form',
    ]); ?>

    <?= $form->field($model, 'password')->passwordInput([
        'autocomplete' => 'new-password',
    ]) ?>

    <?= $form->field($model, 'password_repeat')->passwordInput([
        'autocomplete' => 'new-password',
    ]) ?>

    <div class="form-group">
        <?= Html::submitButton('Зберегти пароль', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>