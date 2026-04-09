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
        'definitions' => [
           // \common\services\checkout\GuestCartImportService::class => \common\services\checkout\GuestCartImportService::class,
            \common\integrations\keycrm\KeyCrmApiClient::class => static function () {
                return new \common\integrations\keycrm\KeyCrmApiClient(
                    baseUrl: \Yii::$app->params['keycrm.baseUrl'],
                    token: \Yii::$app->params['keycrm.token'],
                    timeout: (int)(\Yii::$app->params['keycrm.timeout'] ?? 30),
                );
            },
            \common\integrations\keycrm\mappers\KeyCrmProductMapper::class => \common\integrations\keycrm\mappers\KeyCrmProductMapper::class,
            \common\services\keycrm\KeyCrmProductImportService::class => static function ($container) {
                return new \common\services\keycrm\KeyCrmProductImportService(
                    $container->get(\common\integrations\keycrm\KeyCrmApiClient::class),
                    $container->get(\common\integrations\keycrm\mappers\KeyCrmProductMapper::class),
                );
            },
            \common\mappers\catalog\PublicProductMapper::class => \common\mappers\catalog\PublicProductMapper::class,
            \common\services\catalog\PublicCatalogService::class => static function ($container) {
                return new \common\services\catalog\PublicCatalogService(
                    $container->get(\common\mappers\catalog\PublicProductMapper::class),
                );
            },
            \common\services\checkout\CartProductResolver::class => \common\services\checkout\CartProductResolver::class,
            \common\services\checkout\GuestCartImportService::class => static function ($container) {
                return new \common\services\checkout\GuestCartImportService(
                    $container->get(\common\services\checkout\CartProductResolver::class),
                );
            },
            \common\services\checkout\CheckoutCustomerResolver::class => \common\services\checkout\CheckoutCustomerResolver::class,
            \common\services\checkout\CheckoutSubmitService::class => static function ($container) {
                return new \common\services\checkout\CheckoutSubmitService(
                    $container->get(\common\services\checkout\CheckoutCustomerResolver::class),
                    $container->get(\common\services\payment\PaymentService::class),
                );
            },
        ]
    ],
    'timeZone' => 'Europe/Kyiv',
];
