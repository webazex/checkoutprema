<?php

/** @var \common\models\customer\CustomerModel $customer */
/** @var string $resetUrl */

echo Yii::t('frontend', 'Hello!') . PHP_EOL . PHP_EOL;
echo Yii::t('frontend', 'To set a new password, follow this link:') . PHP_EOL;
echo $resetUrl . PHP_EOL . PHP_EOL;
echo Yii::t('frontend', 'If you did not request password recovery, simply ignore this email.') . PHP_EOL;