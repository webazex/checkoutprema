<?php

use frontend\models\customer\CustomerRestoreRequestForm;

/** @var yii\web\View $this */

/** @var frontend\models\customer\CustomerRestoreRequestForm|null $model */

use yii\helpers\Html;

$this->title = Yii::t('frontend', 'Restore access');
?>

<main class="customer-auth-page customer-restore-page">
    <div class="site-size">
        <section class="customer-auth customer-auth--restore">
            <h1 class="customer-auth__title">
                <?= Html::encode(Yii::t('frontend', 'Access recovery')) ?>
            </h1>

            <div class="customer-restore__intro">
                <p class="customer-restore__text">
                    <?= Html::encode(Yii::t('frontend', 'Enter the email you used when placing your order.')) ?>
                </p>
                <p class="customer-restore__subtext">
                    <?= Html::encode(Yii::t('frontend', 'If an account with this email exists, we will send instructions for setting or resetting your password.')) ?>
                </p>
            </div>

            <form class="customer-auth__form customer-restore__form" id="customer-restore-form" method="post" action="">
                <div class="customer-auth__field">
                    <label class="customer-auth__label" for="restore-email">
                        Email
                    </label>

                    <input
                            class="customer-auth__input"
                            type="email"
                            name="email"
                            id="restore-email"
                            autocomplete="email"
                            required
                    >
                </div>

                <div class="customer-restore__actions">
                    <button class="customer-auth__btn customer-auth__btn--primary" type="button">
                        <?= Html::encode(Yii::t('frontend', 'Send link')) ?>
                    </button>
                </div>

                <div class="customer-restore__links">
                    <?= Html::a(
                            Yii::t('frontend', 'Back to login'),
                            ['/customer/login'],
                            ['class' => 'customer-restore__back-link']
                    ) ?>
                </div>
            </form>
        </section>
    </div>
</main>