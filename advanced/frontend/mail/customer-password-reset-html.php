<?php

use common\models\customer\CustomerModel;
use yii\helpers\Html;

/** @var CustomerModel $customer */
/** @var string $resetUrl */
?>
<p><?= Html::encode(Yii::t('frontend', 'Hello!')) ?></p>

<p>
    <?= Html::encode(Yii::t('frontend', 'To set a new password, follow this link:')) ?>
</p>

<p>
    <a href="<?= Html::encode($resetUrl) ?>"><?= Html::encode($resetUrl) ?></a>
</p>

<p>
    <?= Html::encode(Yii::t('frontend', 'If you did not request password recovery, simply ignore this email.')) ?>
</p>