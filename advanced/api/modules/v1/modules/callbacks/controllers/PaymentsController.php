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

    private function getCallbackPayload(): array
    {
        $request = Yii::$app->request;

        $rawBody = trim((string)$request->rawBody);

        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $post = $request->post();

        if (is_array($post) && isset($post['orderReference'])) {
            return $post;
        }

        /**
         * WayForPay иногда приходит как application/x-www-form-urlencoded,
         * но JSON оказывается ключом POST-массива.
         */
        if (is_array($post) && count($post) === 1) {
            $firstKey = (string)array_key_first($post);

            $restoredJson = $firstKey;

            $firstValue = $post[$firstKey] ?? null;

            if (is_array($firstValue) && count($firstValue) === 1) {
                $secondKey = (string)array_key_first($firstValue);
                $restoredJson .= '[' . $secondKey . ']';
            }

            $decoded = json_decode($restoredJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $post;
    }

    public function actionHandle(string $provider): Response
    {
        $provider = strtolower(trim($provider));
        $payload = $this->getCallbackPayload();

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