<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use common\models\LoginForm;

class SiteController extends Controller
{
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['/site/cabinet', 'id' => Yii::$app->user->id]);
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(['/site/cabinet', 'id' => Yii::$app->user->id]);
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionCabinet(int $id)
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/site/login']);
        }

        if ((int)Yii::$app->user->id !== $id) {
            throw new ForbiddenHttpException('Access denied.');
        }

        return $this->render('cabinet');
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->redirect(['/site/login']);
    }
}