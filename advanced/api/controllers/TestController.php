<?php

namespace api\controllers;

use yii\rest\Controller;
use yii\filters\ContentNegotiator;
use yii\web\Response;

class TestController extends Controller
{
    public function behaviors()
    {
        return [
            [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                    'application/xml'  => Response::FORMAT_XML,
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return [
            'status' => 'ok',
            'message' => 'API работает!',
            'time' => date('c'),
        ];
    }

    public function actionInfo()
    {
        return [
            'app_id' => \Yii::$app->id,
            'env' => YII_ENV,
            'php' => PHP_VERSION,
        ];
    }

    public function actionError()
    {
        return [
            'status' => 'error',
            'message' => 'Who are you?',
            'time' => date('c'),
            'err_code' => 0
        ];
    }
}