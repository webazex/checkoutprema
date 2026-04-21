<?php

declare(strict_types=1);

namespace api\modules\v1\modules\publicApi\controllers;

use common\models\checkout\CheckoutCartStateInput;
use common\services\checkout\CheckoutCartStateService;
use common\services\checkout\CheckoutSubmitService;
use common\services\checkout\GuestCartImportService;
use Yii;
use yii\filters\Cors;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

final class CheckoutController extends Controller
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => [
                    'https://www.premabrand.com.ua',
                    'https://premabrand.com.ua',
                ],
                'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => false,
            ],
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'import-cart' => ['POST', 'OPTIONS'],
                'cart-state' => ['POST', 'OPTIONS'],
                'submit' => ['POST', 'OPTIONS'],
                'payment-return' => ['GET', 'OPTIONS'],
                'options' => ['OPTIONS'],
            ],
        ];

        return $behaviors;
    }

    public function actions(): array
    {
        return ArrayHelper::merge(parent::actions(), [
            'options' => [
                'class' => 'yii\rest\OptionsAction',
            ],
        ]);
    }

    public function actionImportCart(): array
    {
        /** @var GuestCartImportService $service */
        $service = Yii::$container->get(GuestCartImportService::class);

        $payload = Yii::$app->request->getBodyParams();
        $result = $service->import($payload);

        Yii::$app->response->statusCode = 200;

        return [
            'status' => 'ok',
            'message' => 'Cart imported successfully.',
            'data' => $result,
        ];
    }

    public function actionCartState(): array
    {
        $input = new CheckoutCartStateInput();
        $input->load(Yii::$app->request->getBodyParams(), '');

        if (!$input->validate()) {
            throw new BadRequestHttpException(json_encode($input->getFirstErrors(), JSON_UNESCAPED_UNICODE));
        }

        /** @var CheckoutCartStateService $service */
        $service = Yii::$container->get(CheckoutCartStateService::class);

        $dto = $service->getActiveCartBySession(
            sessionKey: (string)$input->sessionKey,
            sourceType: (string)$input->sourceType,
        );

        Yii::$app->response->statusCode = 200;

        return [
            'status' => 'ok',
            'message' => 'Cart state loaded.',
            'data' => $dto->toArray(),
        ];
    }

    public function actionSubmit(): array
    {
        /** @var CheckoutSubmitService $service */
        $service = Yii::$container->get(CheckoutSubmitService::class);

        $payload = Yii::$app->request->getBodyParams();
        $callbackUrl = Yii::$app->request->hostInfo . '/api/v1/callbacks/payments/';
        $defaultReturnUrl = Yii::$app->request->hostInfo . '/checkout/payment-return';

        $result = $service->submit(
            payload: $payload,
            callbackUrl: $callbackUrl,
            defaultReturnUrl: $defaultReturnUrl,
        );

        Yii::$app->response->statusCode = 200;

        return [
            'status' => 'ok',
            'message' => 'Checkout submitted successfully.',
            'data' => $result,
        ];
    }

    public function actionPaymentReturn(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return [
            'status' => 'ok',
            'message' => 'Payment return received.',
            'data' => [
                'query' => Yii::$app->request->get(),
            ],
        ];
    }
}