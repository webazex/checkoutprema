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
    ],

    'container' => [
        'singletons' => $singletones,
        'definitions' => $definitions,
    ],

    'timeZone' => 'Europe/Kyiv',
    'language' => 'uk',
];