<?php

use common\contracts\delivery\DeliveryPointStorageInterface;
use common\integrations\keycrm\KeyCrmApiClient;
use common\integrations\keycrm\mappers\KeyCrmCategoryMapper;
use common\integrations\keycrm\mappers\KeyCrmProductMapper;
use common\integrations\novaposhta\NovaPoshtaApiClient;
use common\integrations\novaposhta\NovaPoshtaDeliveryProvider;
use common\integrations\novaposhta\NovaPoshtaDirectorySource;
use common\mappers\cart\CheckoutCartMapper;
use common\mappers\catalog\PublicProductMapper;
use common\mappers\delivery\DeliveryReadMapper;
use common\mappers\novaposhta\NovaPoshtaDirectoryMapper;
use common\mappers\novaposhta\NovaPoshtaResponseMapper;
use common\services\cart\CheckoutCartViewService;
use common\services\catalog\PublicCatalogService;
use common\services\checkout\CartProductResolver;
use common\services\checkout\CheckoutCustomerResolver;
use common\services\checkout\CheckoutSubmitService;
use common\services\checkout\GuestCartImportService;
use common\services\delivery\DeliveryProviderRegistry;
use common\services\delivery\DeliverySyncSchedulerService;
use common\services\delivery\DeliverySyncService;
use common\services\delivery\DeliverySyncStatusService;
use common\services\keycrm\KeyCrmCategorySyncService;
use common\services\keycrm\KeyCrmCustomerSyncService;
use common\services\keycrm\KeyCrmOrderExportService;
use common\services\keycrm\KeyCrmProductImportService;
use common\services\keycrm\KeyCrmRateLimiter;
use common\services\keycrm\KeyCrmStockWebhookService;
use common\services\novaposhta\NovaPoshtaApiService;
use common\services\novaposhta\NovaPoshtaDeliveryService;
use common\services\novaposhta\NovaPoshtaRateLimiter;
use common\services\order\OrderPostPaymentProcessor;
use common\services\payment\PaymentService;
use common\storages\delivery\DeliveryAreaStorage;
use common\storages\delivery\DeliveryDirectoryWriteStorage;
use common\storages\delivery\DeliveryPointStorage;
use common\storages\delivery\DeliveryPointTypeStorage;
use common\storages\delivery\DeliveryProviderStorage;
use common\storages\delivery\DeliverySettlementStorage;
use common\storages\delivery\DeliverySyncStateStorage;
use yii\di\Container;

return [
    KeyCrmApiClient::class => static function (Container $container) {
        return new KeyCrmApiClient(
            baseUrl: Yii::$app->params['keycrm.baseUrl'],
            token: Yii::$app->params['keycrm.token'],
            timeout: (int)(Yii::$app->params['keycrm.timeout'] ?? 30),
            rateLimiter: $container->get(KeyCrmRateLimiter::class),
        );
    },

    KeyCrmProductMapper::class
    => KeyCrmProductMapper::class,

    KeyCrmProductImportService::class => static function ($container) {
        return new KeyCrmProductImportService(
            $container->get(KeyCrmApiClient::class),
            $container->get(KeyCrmProductMapper::class),
        );
    },

    PublicProductMapper::class
    => PublicProductMapper::class,

    PublicCatalogService::class => static function ($container) {
        return new PublicCatalogService(
            $container->get(PublicProductMapper::class),
        );
    },

    CartProductResolver::class
    => CartProductResolver::class,

    GuestCartImportService::class => static function ($container) {
        return new GuestCartImportService(
            $container->get(CartProductResolver::class),
        );
    },

    CheckoutCartMapper::class
    => CheckoutCartMapper::class,

    CheckoutCartViewService::class => static function ($container) {
        return new CheckoutCartViewService(
            $container->get(CheckoutCartMapper::class),
        );
    },

    CheckoutCustomerResolver::class
    => CheckoutCustomerResolver::class,

    CheckoutSubmitService::class => static function ($container) {
        return new CheckoutSubmitService(
            $container->get(CheckoutCustomerResolver::class),
            $container->get(PaymentService::class),
        );
    },
    KeyCrmStockWebhookService::class => KeyCrmStockWebhookService::class,
    KeyCrmCustomerSyncService::class => static function ($container) {
        return new KeyCrmCustomerSyncService(
            $container->get(KeyCrmApiClient::class),
        );
    },
    KeyCrmOrderExportService::class => static function ($container) {
        return new KeyCrmOrderExportService(
            $container->get(KeyCrmApiClient::class),
        );
    },
    OrderPostPaymentProcessor::class => static function ($container) {
        return new OrderPostPaymentProcessor(
            $container->get(KeyCrmCustomerSyncService::class),
            $container->get(KeyCrmOrderExportService::class),
        );
    },

    KeyCrmCategoryMapper::class => KeyCrmCategoryMapper::class,

    KeyCrmCategorySyncService::class => static function ($container) {
        return new KeyCrmCategorySyncService(
            $container->get(KeyCrmApiClient::class),
            $container->get(KeyCrmCategoryMapper::class),
        );
    },
    KeyCrmRateLimiter::class => static function () {
        return new KeyCrmRateLimiter(
            minIntervalMs: (int)(Yii::$app->params['keycrm.rateLimitIntervalMs'] ?? 2000),
            lockTimeoutSeconds: (int)(Yii::$app->params['keycrm.rateLimitLockTimeout'] ?? 10),
        );
    },
    NovaPoshtaRateLimiter::class => static function () {
        return new NovaPoshtaRateLimiter(
            minIntervalMs: (int)(Yii::$app->params['novaPoshta.rateLimitIntervalMs'] ?? 1000),
            lockTimeoutSeconds: (int)(Yii::$app->params['novaPoshta.rateLimitLockTimeout'] ?? 10),
        );
    },

    NovaPoshtaApiClient::class => static function (Container $container) {
        return new NovaPoshtaApiClient(
            baseUrl: (string)(Yii::$app->params['novaPoshta.baseUrl'] ?? ''),
            apiKey: (string)(Yii::$app->params['novaPoshta.apiKey'] ?? ''),
            timeout: (int)(Yii::$app->params['novaPoshta.timeout'] ?? 15),
            rateLimiter: $container->get(NovaPoshtaRateLimiter::class),
            maxAttempts: (int)(Yii::$app->params['novaPoshta.maxAttempts'] ?? 3),
            retryBaseDelayMs: (int)(Yii::$app->params['novaPoshta.retryBaseDelayMs'] ?? 500),
            retryMaxDelayMs: (int)(Yii::$app->params['novaPoshta.retryMaxDelayMs'] ?? 5000),
            retryJitterMs: (int)(Yii::$app->params['novaPoshta.retryJitterMs'] ?? 250),
        );
    },

    NovaPoshtaApiService::class
    => NovaPoshtaApiService::class,

    NovaPoshtaResponseMapper::class
    => NovaPoshtaResponseMapper::class,

    NovaPoshtaDeliveryService::class
    => NovaPoshtaDeliveryService::class,


    DeliveryReadMapper::class
    => DeliveryReadMapper::class,

    DeliveryProviderStorage::class
    => DeliveryProviderStorage::class,

    DeliveryPointTypeStorage::class
    => DeliveryPointTypeStorage::class,

    DeliveryAreaStorage::class
    => DeliveryAreaStorage::class,

    DeliverySettlementStorage::class
    => DeliverySettlementStorage::class,

    DeliveryPointStorage::class
    => DeliveryPointStorage::class,

    DeliveryPointStorageInterface::class
    => static function (Container $container) {
        return $container->get(
            DeliveryPointStorage::class
        );
    },

    DeliverySyncStateStorage::class
    => DeliverySyncStateStorage::class,

    NovaPoshtaDirectoryMapper::class
    => NovaPoshtaDirectoryMapper::class,

    NovaPoshtaDirectorySource::class
    => NovaPoshtaDirectorySource::class,

    NovaPoshtaDeliveryProvider::class
    => NovaPoshtaDeliveryProvider::class,

    DeliveryDirectoryWriteStorage::class
    => DeliveryDirectoryWriteStorage::class,


    DeliverySyncService::class
    => DeliverySyncService::class,

    DeliverySyncSchedulerService::class
    => DeliverySyncSchedulerService::class,

    DeliveryProviderRegistry::class
    => static function (Container $container): DeliveryProviderRegistry {
        return new DeliveryProviderRegistry([
            $container->get(
                NovaPoshtaDeliveryProvider::class
            ),
        ]);
    },

    DeliverySyncStatusService::class
    => DeliverySyncStatusService::class,
];