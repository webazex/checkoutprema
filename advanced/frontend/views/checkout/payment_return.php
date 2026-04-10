<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $query */
/** @var array $post */
/** @var string $method */

$this->title = 'Payment return';
?>

<h1>Payment return</h1>

<p>Method: <strong><?= Html::encode($method) ?></strong></p>

<h2>GET</h2>
<pre><?= Html::encode(print_r($query, true)) ?></pre>

<h2>POST</h2>
<pre><?= Html::encode(print_r($post, true)) ?></pre>