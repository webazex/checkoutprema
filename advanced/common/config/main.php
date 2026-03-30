<?php

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
    ],
    'container' => [
        'singletons' => [
            \common\services\payment\gateways\WayForPayGateway::class => static function () {
                return new \common\services\payment\gateways\WayForPayGateway(
                    merchantAccount: \Yii::$app->params['wayforpay.merchantAccount'],
                    merchantSecretKey: \Yii::$app->params['wayforpay.secretKey'],
                    merchantDomainName: \Yii::$app->params['wayforpay.domain'],
                );
            },

            \common\services\payment\PaymentGatewayRegistry::class => static function ($container) {
                return new \common\services\payment\PaymentGatewayRegistry([
                    $container->get(\common\services\payment\gateways\WayForPayGateway::class),
                ]);
            },

            \common\services\payment\PaymentService::class => static function ($container) {
                return new \common\services\payment\PaymentService(
                    $container->get(\common\services\payment\PaymentGatewayRegistry::class),
                );
            },
        ],
    ],
];
