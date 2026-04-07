<?php

declare(strict_types=1);

namespace api\components;

use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

abstract class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        $verbs = $this->verbs();
        if ($verbs !== []) {
            $behaviors['verbs'] = [
                'class' => VerbFilter::class,
                'actions' => $verbs,
            ];
        }

        return $behaviors;
    }

    protected function verbs(): array
    {
        return [];
    }
}