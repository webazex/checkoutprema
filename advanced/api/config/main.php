<?php

use api\modules\v1\Module;
use common\models\User;
use yii\log\FileTarget;

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
            'class' => Module::class,
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
            'identityClass' => User::class,
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
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
                [
                    'class' => FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => [
                        'keycrm.stock.webhook',
                    ],
                    'logFile' => '@api/runtime/logs/keycrm-stock-webhook.log',
                    'logVars' => [],
                    'prefix' => static fn($message): string => '',
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

                'POST v1/callbacks/payments/<provider:[A-Za-z0-9_-]+>' => 'v1/callbacks/payments/handle',
                'OPTIONS v1/callbacks/payments/<provider:[A-Za-z0-9_-]+>' => 'v1/callbacks/payments/options',

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
                'POST v1/integrations/keycrm/stocks/<token:[A-Za-z0-9_-]{16,128}>' => 'v1/integrations/keycrm/stocks',

                'GET v1/public/catalog/version' => 'v1/public/catalog/version',
                'OPTIONS v1/public/catalog/version' => 'v1/public/catalog/options',

                'POST v1/public/checkout/cart/state' => 'v1/public/checkout/cart-state',
                'OPTIONS v1/public/checkout/cart/state' => 'v1/public/checkout/options',
            ],
        ],
    ],
    'params' => $params,
];