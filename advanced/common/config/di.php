<?php
return [
    \common\integrations\keycrm\KeyCrmApiClient::class => static function () {
        return new \common\integrations\keycrm\KeyCrmApiClient(
            baseUrl: \Yii::$app->params['keycrm.baseUrl'],
            token: \Yii::$app->params['keycrm.token'],
            timeout: (int)(\Yii::$app->params['keycrm.timeout'] ?? 30),
        );
    },

    \common\integrations\keycrm\mappers\KeyCrmProductMapper::class
    => \common\integrations\keycrm\mappers\KeyCrmProductMapper::class,

    \common\services\keycrm\KeyCrmProductImportService::class => static function ($container) {
        return new \common\services\keycrm\KeyCrmProductImportService(
            $container->get(\common\integrations\keycrm\KeyCrmApiClient::class),
            $container->get(\common\integrations\keycrm\mappers\KeyCrmProductMapper::class),
        );
    },

    \common\mappers\catalog\PublicProductMapper::class
    => \common\mappers\catalog\PublicProductMapper::class,

    \common\services\catalog\PublicCatalogService::class => static function ($container) {
        return new \common\services\catalog\PublicCatalogService(
            $container->get(\common\mappers\catalog\PublicProductMapper::class),
        );
    },

    \common\services\checkout\CartProductResolver::class
    => \common\services\checkout\CartProductResolver::class,

    \common\services\checkout\GuestCartImportService::class => static function ($container) {
        return new \common\services\checkout\GuestCartImportService(
            $container->get(\common\services\checkout\CartProductResolver::class),
        );
    },

    \common\mappers\cart\CheckoutCartMapper::class
    => \common\mappers\cart\CheckoutCartMapper::class,

    \common\services\cart\CheckoutCartViewService::class => static function ($container) {
        return new \common\services\cart\CheckoutCartViewService(
            $container->get(\common\mappers\cart\CheckoutCartMapper::class),
        );
    },

    \common\services\checkout\CheckoutCustomerResolver::class
    => \common\services\checkout\CheckoutCustomerResolver::class,

    \common\services\checkout\CheckoutSubmitService::class => static function ($container) {
        return new \common\services\checkout\CheckoutSubmitService(
            $container->get(\common\services\checkout\CheckoutCustomerResolver::class),
            $container->get(\common\services\payment\PaymentService::class),
        );
    },
    \common\services\keycrm\KeyCrmStockWebhookService::class => \common\services\keycrm\KeyCrmStockWebhookService::class,
    \common\services\keycrm\KeyCrmCustomerSyncService::class => static function ($container) {
        return new \common\services\keycrm\KeyCrmCustomerSyncService(
            $container->get(\common\integrations\keycrm\KeyCrmApiClient::class),
        );
    },
];