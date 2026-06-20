<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'queue'],
    'controllerNamespace' => 'console\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'controllerMap' => [
        'fixture' => [
            'class' => \yii\console\controllers\FixtureController::class,
            'namespace' => 'common\fixtures',
        ],
        'migrate' => [
            'class' => \yii\console\controllers\MigrateController::class,
            'migrationPath' => '@console/migrations',
            'migrationNamespaces' => [
                'yii\queue\db\migrations',
            ],
        ],
        'delivery-queue' => [
            'class' => \yii\queue\cli\Command::class,
            'queue' => 'deliveryQueue',
        ],
    ],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => [
                        'keycrm.sync.*',
                    ],
                    'logFile' => '@console/runtime/logs/keycrm-sync.log',
                    'logVars' => [],
                    'prefix' => static fn ($message): string => '',
                ],
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => [
                        'delivery.sync*',
                    ],
                    'logFile' => '@console/runtime/logs/delivery-sync.log',
                    'logVars' => [],
                    'prefix' => static fn ($message): string => '',
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