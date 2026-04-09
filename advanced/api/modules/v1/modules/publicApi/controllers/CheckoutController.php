<?php

declare(strict_types=1);

namespace api\modules\v1\modules\publicApi\controllers;

use api\components\ApiController;
use common\models\payment\PaymentModel;
use common\services\checkout\CheckoutSubmitService;
use common\services\checkout\GuestCartImportService;
use DomainException;
use InvalidArgumentException;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnprocessableEntityHttpException;

final class CheckoutController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'import-cart' => ['POST'],
            'submit' => ['POST'],
            'payment-return' => ['GET'],
        ];
    }

    public function actionImportCart(): array
    {
        $payload = Yii::$app->request->bodyParams;

        if ($payload === []) {
            throw new BadRequestHttpException('Empty import cart payload.');
        }

        try {
            /** @var GuestCartImportService $service */
            $service = Yii::$container->get(GuestCartImportService::class);
            $result = $service->import($payload);

            $checkoutUrl = Yii::$app->request->hostInfo . '/checkout/' . $result['hash'];
            $result['checkoutUrl'] = $checkoutUrl;

            return [
                'status' => 'ok',
                'message' => 'Cart imported successfully.',
                'data' => $result,
            ];
        } catch (InvalidArgumentException | DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), 0, $e);
        }
    }

    public function actionSubmit(): array
    {
        $payload = Yii::$app->request->bodyParams;

        if ($payload === []) {
            throw new BadRequestHttpException('Empty checkout payload.');
        }

        $hostInfo = Yii::$app->request->hostInfo;
        $callbackUrl = $hostInfo . '/api/v1/callbacks/payments/' . PaymentModel::PROVIDER_WAYFORPAY;
        $defaultReturnUrl = $hostInfo . '/api/v1/public/checkout/payment-return';

        try {
            /** @var CheckoutSubmitService $service */
            $service = Yii::$container->get(CheckoutSubmitService::class);

            $result = $service->submit(
                payload: $payload,
                callbackUrl: $callbackUrl,
                defaultReturnUrl: $defaultReturnUrl,
            );

            return [
                'status' => 'ok',
                'message' => 'Checkout submitted successfully.',
                'data' => $result,
            ];
        } catch (InvalidArgumentException | DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), 0, $e);
        }
    }

    public function actionPaymentReturn(): array
    {
        return [
            'status' => 'ok',
            'message' => 'Payment return endpoint reached.',
            'data' => Yii::$app->request->get(),
        ];
    }
}