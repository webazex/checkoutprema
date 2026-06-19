<?php
return [
    \common\integrations\keycrm\KeyCrmApiClient::class => static function (\yii\di\Container $container) {
        return new \common\integrations\keycrm\KeyCrmApiClient(
            baseUrl: \Yii::$app->params['keycrm.baseUrl'],
            token: \Yii::$app->params['keycrm.token'],
            timeout: (int)(\Yii::$app->params['keycrm.timeout'] ?? 30),
            rateLimiter: $container->get(\common\services\keycrm\KeyCrmRateLimiter::class),
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
    \common\services\keycrm\KeyCrmOrderExportService::class => static function ($container) {
        return new \common\services\keycrm\KeyCrmOrderExportService(
            $container->get(\common\integrations\keycrm\KeyCrmApiClient::class),
        );
    },
    \common\services\order\OrderPostPaymentProcessor::class => static function ($container) {
        return new \common\services\order\OrderPostPaymentProcessor(
            $container->get(\common\services\keycrm\KeyCrmCustomerSyncService::class),
            $container->get(\common\services\keycrm\KeyCrmOrderExportService::class),
        );
    },

    \common\integrations\keycrm\mappers\KeyCrmCategoryMapper::class => \common\integrations\keycrm\mappers\KeyCrmCategoryMapper::class,

    \common\services\keycrm\KeyCrmCategorySyncService::class => static function ($container) {
        return new \common\services\keycrm\KeyCrmCategorySyncService(
            $container->get(\common\integrations\keycrm\KeyCrmApiClient::class),
            $container->get(\common\integrations\keycrm\mappers\KeyCrmCategoryMapper::class),
        );
    },
    \common\services\keycrm\KeyCrmRateLimiter::class => static function () {
        return new \common\services\keycrm\KeyCrmRateLimiter(
            minIntervalMs: (int)(\Yii::$app->params['keycrm.rateLimitIntervalMs'] ?? 2000),
            lockTimeoutSeconds: (int)(\Yii::$app->params['keycrm.rateLimitLockTimeout'] ?? 10),
        );
    },
    \common\services\novaposhta\NovaPoshtaRateLimiter::class => static function () {
        return new \common\services\novaposhta\NovaPoshtaRateLimiter(
            minIntervalMs: (int)(\Yii::$app->params['novaPoshta.rateLimitIntervalMs'] ?? 1000),
            lockTimeoutSeconds: (int)(\Yii::$app->params['novaPoshta.rateLimitLockTimeout'] ?? 10),
        );
    },

    \common\integrations\novaposhta\NovaPoshtaApiClient::class => static function (\yii\di\Container $container) {
        return new \common\integrations\novaposhta\NovaPoshtaApiClient(
            baseUrl: (string)(\Yii::$app->params['novaPoshta.baseUrl'] ?? ''),
            apiKey: (string)(\Yii::$app->params['novaPoshta.apiKey'] ?? ''),
            timeout: (int)(\Yii::$app->params['novaPoshta.timeout'] ?? 15),
            rateLimiter: $container->get(\common\services\novaposhta\NovaPoshtaRateLimiter::class),
            maxAttempts: (int)(\Yii::$app->params['novaPoshta.maxAttempts'] ?? 3),
            retryBaseDelayMs: (int)(\Yii::$app->params['novaPoshta.retryBaseDelayMs'] ?? 500),
            retryMaxDelayMs: (int)(\Yii::$app->params['novaPoshta.retryMaxDelayMs'] ?? 5000),
            retryJitterMs: (int)(\Yii::$app->params['novaPoshta.retryJitterMs'] ?? 250),
        );
    },

    \common\services\novaposhta\NovaPoshtaApiService::class
    => \common\services\novaposhta\NovaPoshtaApiService::class,

    \common\mappers\novaposhta\NovaPoshtaResponseMapper::class
    => \common\mappers\novaposhta\NovaPoshtaResponseMapper::class,

    \common\services\novaposhta\NovaPoshtaDeliveryService::class
    => \common\services\novaposhta\NovaPoshtaDeliveryService::class,


    \common\mappers\delivery\DeliveryReadMapper::class
    => \common\mappers\delivery\DeliveryReadMapper::class,

    \common\storages\delivery\DeliveryProviderStorage::class
    => \common\storages\delivery\DeliveryProviderStorage::class,

    \common\storages\delivery\DeliveryPointTypeStorage::class
    => \common\storages\delivery\DeliveryPointTypeStorage::class,

    \common\storages\delivery\DeliveryAreaStorage::class
    => \common\storages\delivery\DeliveryAreaStorage::class,

    \common\storages\delivery\DeliverySettlementStorage::class
    => \common\storages\delivery\DeliverySettlementStorage::class,

    \common\storages\delivery\DeliveryPointStorage::class
    => \common\storages\delivery\DeliveryPointStorage::class,

    \common\contracts\delivery\DeliveryPointStorageInterface::class
    => static function (\yii\di\Container $container) {
        return $container->get(
            \common\storages\delivery\DeliveryPointStorage::class
        );
    },

    \common\storages\delivery\DeliverySyncStateStorage::class
    => \common\storages\delivery\DeliverySyncStateStorage::class,

    \common\mappers\novaposhta\NovaPoshtaDirectoryMapper::class
    => \common\mappers\novaposhta\NovaPoshtaDirectoryMapper::class,
];