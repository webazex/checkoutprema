<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
// require __DIR__ . '/params-local.php',
);

return [
    'id' => 'app-api',
    'basePath' => __DIR__ . '/..',
    'bootstrap' => ['log'],
    'controllerNamespace' => 'api\controllers',

    'aliases' => [
        '@api' => '@app',
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
            'identityClass' => 'common\models\User',   // или свой Customer, если API для покупателей
            'enableAutoLogin' => false,                // обычно для API false
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'session' => [
            // для API обычно не нужна, но если вдруг — можно отключить
            'name' => 'advanced-api',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
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
            'enableStrictParsing' => true,          // важно для чистого REST
            'rules' => [
                '' => 'test/index',
                'GET test' => 'test/index',
                'GET test/info' => 'test/info',
            ],
        ],
    ],

    'params' => $params,
];