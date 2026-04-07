<?php

declare(strict_types=1);

namespace api\modules\v1\modules\publicApi\controllers;

use api\components\ApiController;

final class CatalogController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'view' => ['GET'],
        ];
    }

    public function actionIndex(): array
    {
        return [
            'status' => 'ok',
            'message' => 'Catalog endpoint stub.',
            'items' => [],
        ];
    }

    public function actionView(int $id): array
    {
        return [
            'status' => 'ok',
            'message' => 'Catalog item endpoint stub.',
            'id' => $id,
        ];
    }
}