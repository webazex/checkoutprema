<?php

use yii\caching\FileCache;
use yii\i18n\PhpMessageSource;
use yii\log\FileTarget;
use yii\mutex\MysqlMutex;
use yii\queue\db\Queue;
use yii\queue\LogBehavior;

$diPath = __DIR__ . '/di.php';
$singlePath = __DIR__ . '/singletones.php';

$definitions = file_exists($diPath) ? require $diPath : [];
$singletones = file_exists($singlePath) ? require $singlePath : [];

return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],

    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',

    'components' => [
        'cache' => [
            'class' => FileCache::class,
        ],

        'mutex' => [
            'class' => MysqlMutex::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => [
                        'novaposhta.api.*',
                    ],
                    'logFile' => '@runtime/logs/novaposhta-api.log',
                    'logVars' => [],
                    'prefix' => static fn($message): string => '',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                ],
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => [
                        'delivery.sync*',
                    ],
                    'logVars' => [],
                    'prefix' => static fn($message): string => '',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                ],
            ],
        ],

        'i18n' => [
            'translations' => [
                'frontend*' => [
                    'class' => PhpMessageSource::class,
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en-US',
                    'fileMap' => [
                        'frontend' => 'frontend.php',
                    ],
                ],
            ],
        ],

        'queue' => [
            'class' => Queue::class,
            'db' => 'db',
            'tableName' => '{{%queue}}',
            'channel' => 'keycrm',
            'mutex' => [
                'class' => MysqlMutex::class,
            ],
            'as log' => LogBehavior::class,
        ],
        'deliveryQueue' => [
            'class' => Queue::class,
            'db' => 'db',
            'tableName' => '{{%queue}}',
            'channel' => 'delivery',
            'mutex' => [
                'class' => MysqlMutex::class,
            ],
            'as log' => LogBehavior::class,
        ],
    ],

    'container' => [
        'singletons' => $singletones,
        'definitions' => $definitions,
    ],

    'timeZone' => 'Europe/Kyiv',
    'language' => 'uk',
];