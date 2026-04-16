<?php
$diPatch = __DIR__ . '/di.php';
$singlePatch = __DIR__ . '/singletones.php';
$definitions = (file_exists($diPatch))? require $diPatch : [];
$singletones = (file_exists($singlePatch))? require $singlePatch : [];
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
    ],
    'container' => [
        'singletons' => $singletones,
        'definitions' => $definitions,
    ],
    'timeZone' => 'Europe/Kyiv',
];