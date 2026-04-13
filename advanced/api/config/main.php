<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

return [
    'id' => 'app-api',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'Europe/Simferopol',
    'controllerNamespace' => 'api\controllers',
    'aliases' => [
        '@api' => '@app',
    ],
    'modules' => [
        'v1' => [
            'class' => \api\modules\v1\Module::class,
        ],
    ],
    'components' => [
        'request' => [
            'baseUrl' => '/api',
            'csrfParam' => '_csrf-api',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            'enableAutoLogin' => false,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'session' => [
            'name' => 'advanced-api',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'default/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                '' => 'default/index',
                'GET info' => 'default/info',
                'GET v1' => 'v1/default/index',
                'GET v1/ping' => 'v1/default/ping',

                'POST v1/callbacks/payments/' => 'v1/callbacks/payments/handle',

                'GET v1/public/catalog/items' => 'v1/public/catalog/index',
                'OPTIONS v1/public/catalog/items' => 'v1/public/catalog/options',

                'GET v1/public/catalog/items/<id:\d+>' => 'v1/public/catalog/view',
                'OPTIONS v1/public/catalog/items/<id:\d+>' => 'v1/public/catalog/options',

                'POST v1/public/checkout/cart/import' => 'v1/public/checkout/import-cart',
                'OPTIONS v1/public/checkout/cart/import' => 'v1/public/checkout/options',

                'POST v1/public/checkout/submit' => 'v1/public/checkout/submit',
                'OPTIONS v1/public/checkout/submit' => 'v1/public/checkout/options',

                'GET v1/public/checkout/payment-return' => 'v1/public/checkout/payment-return',
                'OPTIONS v1/public/checkout/payment-return' => 'v1/public/checkout/options',

                'GET v1/integrations' => 'v1/integrations/default/index',
            ],
        ],
    ],
    'params' => $params,
];