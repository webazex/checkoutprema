<?php

namespace frontend\assets;

use yii\web\AssetBundle;

final class CheckoutAsset extends AssetBundle
{
    public $basePath = '@webroot';

    public $baseUrl = '@web';

    public $css = [
        'css/checkout.css',
    ];

    public $js = [
        'js/checkout.js',
    ];

    public $depends = [
        AppAsset::class,
    ];
}