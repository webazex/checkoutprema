<?php

declare(strict_types=1);

namespace api\controllers;

use api\components\ApiController;
use Throwable;
use Yii;

final class DefaultController extends ApiController
{
    public function actionIndex(): array
    {
        return [
            'status' => 'ok',
            'message' => 'CheckoutPrema API is running.',
            'version_root' => '/api/v1',
            'time' => date('c'),
        ];
    }

    public function actionInfo(): array
    {
//        return [
//            'app_id' => Yii::$app->id,
//            'env' => YII_ENV,
//            'php' => PHP_VERSION,
//            'timeZone' => Yii::$app->timeZone,
//            'time' => date('c'),
//        ];
        $dbComponent = null;
        $dbDsn = null;
        $dbClass = null;
        $dbError = null;

        try {
            $db = Yii::$app->db;
            $dbClass = get_class($db);
            $dbDsn = $db->dsn ?? null;
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }

        return [
            'app_id' => Yii::$app->id,
            'env' => YII_ENV,
            'php' => PHP_VERSION,
            'timeZone' => Yii::$app->timeZone,
            'dbClass' => $dbClass,
            'dbDsn' => $dbDsn,
            'dbError' => $dbError,
            'time' => date('c'),
        ];
    }

    public function actionError(): array
    {
        $exception = Yii::$app->errorHandler->exception;

        return [
            'status' => 'error',
            'message' => $exception?->getMessage() ?? 'Unknown API error.',
            'type' => $exception ? get_class($exception) : null,
            'code' => $exception?->getCode(),
            'time' => date('c'),
        ];
    }

    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'info' => ['GET'],
            'error' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        ];
    }
}