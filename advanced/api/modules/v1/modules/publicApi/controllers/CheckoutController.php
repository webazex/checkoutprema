<?php

declare(strict_types=1);

namespace api\modules\v1\modules\publicApi\controllers;

use api\components\ApiController;
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

            return [
                'status' => 'ok',
                'message' => 'Cart imported successfully.',
                'data' => $result,
            ];
        } catch (InvalidArgumentException|DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), 0, $e);
        }
    }

    public function actionSubmit(): array
    {
        $payload = Yii::$app->request->bodyParams;

        if ($payload === []) {
            throw new BadRequestHttpException('Empty checkout payload.');
        }

        return [
            'status' => 'ok',
            'message' => 'Checkout submit stub.',
            'payload' => $payload,
        ];
    }
}