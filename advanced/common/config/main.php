<?php

$diPath = __DIR__ . '/di.php';
$singlePath = __DIR__ . '/singletones.php';

$definitions = file_exists($diPath) ? require $diPath : [];
$singletones = file_exists($singlePath) ? require $singlePath : [];

return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],

    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',

    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],

        'mutex' => [
            'class' => \yii\mutex\MysqlMutex::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => [
                        'novaposhta.api.*',
                    ],
                    'logFile' => '@runtime/logs/novaposhta-api.log',
                    'logVars' => [],
                    'prefix' => static fn ($message): string => '',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                ],
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => [
                        'delivery.sync*',
                    ],
                    'logVars' => [],
                    'prefix' => static fn ($message): string => '',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                ],
            ],
        ],

        'i18n' => [
            'translations' => [
                'frontend*' => [
                    'class' => \yii\i18n\PhpMessageSource::class,
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en-US',
                    'fileMap' => [
                        'frontend' => 'frontend.php',
                    ],
                ],
            ],
        ],

        'queue' => [
            'class' => \yii\queue\db\Queue::class,
            'db' => 'db',
            'tableName' => '{{%queue}}',
            'channel' => 'keycrm',
            'mutex' => [
                'class' => \yii\mutex\MysqlMutex::class,
            ],
            'as log' => \yii\queue\LogBehavior::class,
        ],
        'deliveryQueue' => [
            'class' => \yii\queue\db\Queue::class,
            'db' => 'db',
            'tableName' => '{{%queue}}',
            'channel' => 'delivery',
            'mutex' => [
                'class' => \yii\mutex\MysqlMutex::class,
            ],
            'as log' => \yii\queue\LogBehavior::class,
        ],
    ],

    'container' => [
        'singletons' => $singletones,
        'definitions' => $definitions,
    ],

    'timeZone' => 'Europe/Kyiv',
    'language' => 'uk',
];