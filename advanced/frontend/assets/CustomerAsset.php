<?php

namespace frontend\assets;

use yii\web\AssetBundle;

class CustomerAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';

    public $css = [
        'css/customer.css',
    ];

    public $js = [
        'js/customer.js',
    ];

    public $depends = [
        AppAsset::class,
    ];
}