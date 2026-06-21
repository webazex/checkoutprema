<?php

declare(strict_types=1);

namespace api\modules\v1\modules\integrations\controllers;

use api\components\ApiController;
use common\services\keycrm\KeyCrmStockWebhookService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

final class KeycrmController extends ApiController
{
    public function actionStocks(string $token): array
    {
        $expectedToken = (string)(Yii::$app->params['keycrm.webhookToken'] ?? '');

        if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
            throw new ForbiddenHttpException('Invalid webhook token.');
        }

        $payload = Yii::$app->request->bodyParams;

        if (!is_array($payload)) {
            throw new BadRequestHttpException('Invalid stock webhook payload.');
        }

        /** @var KeyCrmStockWebhookService $service */
        $service = Yii::$container->get(KeyCrmStockWebhookService::class);

        $result = $service->handle($payload);

        return [
            'status' => 'ok',
            'message' => 'KeyCRM stock webhook processed.',
            'data' => $result,
        ];
    }

    protected function verbs(): array
    {
        return [
            'stocks' => ['POST'],
        ];
    }
}