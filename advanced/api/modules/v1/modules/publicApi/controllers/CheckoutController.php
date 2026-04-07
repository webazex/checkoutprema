<?php

declare(strict_types=1);

namespace api\modules\v1\modules\publicApi\controllers;

use api\components\ApiController;
use Yii;
use yii\web\BadRequestHttpException;

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

        return [
            'status' => 'ok',
            'message' => 'Checkout cart import stub.',
            'payload' => $payload,
        ];
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