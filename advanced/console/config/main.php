<?php

use yii\console\controllers\FixtureController;
use yii\console\controllers\MigrateController;
use yii\log\FileTarget;

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        'queue',
        'deliveryQueue',
    ],
    'controllerNamespace' => 'console\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'controllerMap' => [
        'fixture' => [
            'class' => FixtureController::class,
            'namespace' => 'common\fixtures',
        ],
        'migrate' => [
            'class' => MigrateController::class,
            'migrationPath' => '@console/migrations',
            'migrationNamespaces' => [
                'yii\queue\db\migrations',
            ],
        ],
    ],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => [
                        'keycrm.sync.*',
                    ],
                    'logFile' => '@console/runtime/logs/keycrm-sync.log',
                    'logVars' => [],
                    'prefix' => static fn($message): string => '',
                ],
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => [
                        'delivery.sync*',
                    ],
                    'logFile' => '@console/runtime/logs/delivery-sync.log',
                    'logVars' => [],
                    'prefix' => static fn($message): string => '',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                ],
            ],
        ],
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],
    ],
    'params' => $params,
];