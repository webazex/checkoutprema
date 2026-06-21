<?php

use common\services\payment\gateways\WayForPayGateway;
use common\services\payment\PaymentGatewayRegistry;
use common\services\payment\PaymentService;

return [
    WayForPayGateway::class => static function () {
        return new WayForPayGateway(
            merchantAccount: Yii::$app->params['wayforpay.merchantAccount'],
            merchantSecretKey: Yii::$app->params['wayforpay.secretKey'],
            merchantDomainName: Yii::$app->params['wayforpay.domain'],
        );
    },

    PaymentGatewayRegistry::class => static function ($container) {
        return new PaymentGatewayRegistry([
            $container->get(WayForPayGateway::class),
        ]);
    },

    PaymentService::class => static function ($container) {
        return new PaymentService(
            $container->get(PaymentGatewayRegistry::class),
        );
    },
];