<?php

declare(strict_types=1);

namespace api\modules\v1\controllers;

use api\components\ApiController;

final class DefaultController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'ping' => ['GET'],
        ];
    }

    public function actionIndex(): array
    {
        return [
            'status' => 'ok',
            'version' => 'v1',
            'modules' => [
                'public',
                'callbacks',
                'integrations',
            ],
            'time' => date('c'),
        ];
    }

    public function actionPing(): array
    {
        return [
            'status' => 'ok',
            'pong' => true,
            'time' => date('c'),
        ];
    }
}