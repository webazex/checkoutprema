<?php

declare(strict_types=1);

namespace api\modules\v1\modules\publicApi\controllers;

use api\components\ApiController;
use common\services\catalog\PublicCatalogService;
use Yii;
use yii\filters\Cors;
use yii\rest\OptionsAction;

final class CatalogController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'view' => ['GET'],
        ];
    }

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
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => [],
            ],
        ];

        return $behaviors;
    }

    public function actions(): array
    {
        return array_merge(parent::actions(), [
            'options' => [
                'class' => OptionsAction::class,
            ],
        ]);
    }


    public function actionIndex(): array
    {
        $page = (int)Yii::$app->request->get('page', 1);
        $perPage = (int)Yii::$app->request->get('perPage', 20);
        $onlyAvailable = filter_var(
            Yii::$app->request->get('onlyAvailable', false),
            FILTER_VALIDATE_BOOLEAN
        );

        /** @var PublicCatalogService $service */
        $service = Yii::$container->get(PublicCatalogService::class);

        $result = $service->getList(
            page: $page,
            perPage: $perPage,
            onlyAvailable: $onlyAvailable,
        );

        return [
            'status' => 'ok',
            'message' => 'Catalog loaded successfully.',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ];
    }

    public function actionView(int $id): array
    {
        /** @var PublicCatalogService $service */
        $service = Yii::$container->get(PublicCatalogService::class);

        return [
            'status' => 'ok',
            'message' => 'Catalog item loaded successfully.',
            'data' => $service->getItemById($id),
        ];
    }
}