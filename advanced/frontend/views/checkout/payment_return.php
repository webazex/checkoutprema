<?php

/** @var yii\web\View $this */
/** @var array $query */

use yii\helpers\Html;

$this->title = 'Payment return';
?>

<h1>Payment return</h1>

<p>The user has returned from WayForPay.</p>

<pre><?= Html::encode(print_r($query, true)) ?></pre>