<?php

declare(strict_types=1);

namespace api\modules\v1\modules\integrations\controllers;

use api\components\ApiController;

final class DefaultController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
        ];
    }

    public function actionIndex(): array
    {
        return [
            'status' => 'ok',
            'message' => 'Integrations module stub.',
            'time' => date('c'),
        ];
    }
}