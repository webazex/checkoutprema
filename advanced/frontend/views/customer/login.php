<?php

/** @var yii\web\View $this */
/** @var frontend\models\customer\CustomerLoginForm $model */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = Yii::t('frontend', 'Login');
?>

<main class="customer-auth-page customer-login-page">
    <div class="site-size">
        <section class="customer-auth customer-auth--login">
            <h1 class="customer-auth__title">
                <?= Html::encode(Yii::t('frontend', 'Customer account login')) ?>
            </h1>

            <?php $form = ActiveForm::begin([
                    'id' => 'customer-login-form',
                    'options' => [
                            'class' => 'customer-auth__form customer-login__form',
                    ],
                    'fieldConfig' => [
                            'template' => "{input}\n{error}",
                            'options' => [
                                    'class' => 'customer-auth__field',
                            ],
                            'errorOptions' => [
                                    'class' => 'customer-auth__error',
                            ],
                    ],
            ]); ?>

            <?= $form->field($model, 'email')->input('email', [
                    'class' => 'customer-auth__input',
                    'placeholder' => 'Email',
                    'autocomplete' => 'email',
                    'autofocus' => true,
                    'required' => true,
            ]) ?>

            <?= $form->field($model, 'password')->passwordInput([
                    'class' => 'customer-auth__input',
                    'placeholder' => Yii::t('frontend', 'Password'),
                    'autocomplete' => 'current-password',
                    'required' => true,
            ]) ?>

            <div class="customer-auth__remember-row">
                <label class="customer-auth__remember">
                    <?= Html::activeCheckbox($model, 'rememberMe', [
                            'class' => 'customer-auth__remember-input',
                            'label' => false,
                    ]) ?>
                    <span><?= Html::encode($model->getAttributeLabel('rememberMe')) ?></span>
                </label>

                <?= Html::error($model, 'rememberMe', [
                        'class' => 'customer-auth__error',
                ]) ?>
            </div>

            <div class="customer-login__btns-row">
                <button class="customer-auth__btn customer-auth__btn--primary" type="submit">
                    <span><?= Html::encode(Yii::t('frontend', 'Login')) ?></span>
                </button>

                <button class="customer-auth__btn customer-auth__btn--secondary" type="reset">
                    <span><?= Html::encode(Yii::t('frontend', 'Cancel')) ?></span>
                </button>
            </div>

            <div class="customer-login__links">
                <?= Html::a(
                        Html::tag(
                                'span',
                                Html::encode(Yii::t('frontend', 'Restore access')),
                                ['class' => 'recovery-link__txt']
                        ),
                        ['/customer/restore'],
                        ['class' => 'customer-login__recovery-link']
                ) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </section>
    </div>
</main>