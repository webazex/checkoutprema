<?php

declare(strict_types=1);

namespace api\modules\v1\modules\integrations\controllers;

use api\components\ApiController;

final class DefaultController extends ApiController
{
    public function actionIndex(): array
    {
        return [
            'status' => 'ok',
            'message' => 'Integrations module stub.',
            'time' => date('c'),
        ];
    }

    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
        ];
    }
}