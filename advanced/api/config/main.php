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
            'errorAction' => 'test/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
                '' => 'test/index',
                'GET test' => 'test/index',
                'GET test/info' => 'test/info',

                'GET v1' => 'v1/default/index',
                'GET v1/ping' => 'v1/default/ping',

                'POST v1/callbacks/payments/<provider:[a-z0-9-]+>' => 'v1/callbacks/payments/handle',

                'GET v1/public/catalog/items' => 'v1/public/catalog/index',
                'GET v1/public/catalog/items/<id:\d+>' => 'v1/public/catalog/view',

                'POST v1/public/checkout/cart/import' => 'v1/public/checkout/import-cart',
                'POST v1/public/checkout/submit' => 'v1/public/checkout/submit',
            ],
        ],
    ],
    'params' => $params,
];