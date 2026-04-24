<?php

declare(strict_types=1);

namespace api\modules\v1\modules\callbacks\controllers;

use api\components\ApiController;
use common\services\payment\PaymentService;
use Throwable;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

final class PaymentsController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'handle' => ['POST'],
        ];
    }

    public function actionHandle(string $provider): Response
    {
        $provider = strtolower(trim($provider));
        $payload = Yii::$app->request->bodyParams;

        if ($payload === [] && Yii::$app->request->post() !== []) {
            $payload = Yii::$app->request->post();
        }

        if ($payload === []) {
            throw new BadRequestHttpException('Empty callback payload.');
        }

        try {
            /** @var PaymentService $paymentService */
            $paymentService = Yii::$container->get(PaymentService::class);

            $callbackResult = $paymentService->handleCallback($provider, $payload);
            $callbackResponse = $paymentService->buildCallbackResponse($provider, $callbackResult);

            $response = Yii::$app->response;
            $response->format = Response::FORMAT_RAW;
            $response->statusCode = $callbackResponse->statusCode;
            $response->content = $callbackResponse->body;

            foreach ($callbackResponse->headers as $name => $value) {
                $response->headers->set($name, $value);
            }

            return $response;
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Yii::error([
                'message' => 'Payment callback failed.',
                'provider' => $provider,
                'payload' => $payload,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);

            throw new ServerErrorHttpException('Payment callback processing failed.', 0, $e);
        }
    }
}