<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use frontend\models\customer\CustomerLoginForm;
use common\models\customer\CustomerPasswordResetTokenModel;
use frontend\models\customer\CustomerRestoreRequestForm;
use frontend\models\customer\CustomerResetPasswordForm;
use frontend\assets\CustomerAsset;

class CustomerController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post', 'get'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        CustomerAsset::register($this->getView());

        return parent::beforeAction($action);
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect([
                '/customer/view',
                'customerHash' => Yii::$app->user->identity->hash,
            ]);
        }

        $model = new CustomerLoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect([
                '/customer/view',
                'customerHash' => Yii::$app->user->identity->hash,
            ]);
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionRegister()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect([
                '/customer/view',
                'customerHash' => Yii::$app->user->identity->hash,
            ]);
        }

        return $this->render('register');
    }

    public function actionRestore()
    {
        if (!Yii::$app->user->isGuest) {
            $jokes = $this->getRestoreJokes();
            $joke = $jokes[array_rand($jokes)];

            return $this->render('restore-authenticated', [
                'joke' => $joke,
                'customer' => Yii::$app->user->identity,
            ]);
        }

        $model = new CustomerRestoreRequestForm();

        if ($model->load(Yii::$app->request->post()) && $model->process()) {
            Yii::$app->session->setFlash(
                'success',
                'Якщо акаунт із такою поштою існує, ми вже надіслали інструкції для відновлення доступу.'
            );

            return $this->refresh();
        }

        return $this->render('restore', [
            'model' => $model,
        ]);
    }

    public function actionResetPassword(string $token)
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect([
                '/customer/view',
                'customerHash' => Yii::$app->user->identity->hash,
            ]);
        }

        $tokenModel = CustomerPasswordResetTokenModel::findValidByRawToken($token);

        if (!$tokenModel) {
            throw new BadRequestHttpException('Невірне або прострочене посилання для відновлення пароля.');
        }

        $model = new CustomerResetPasswordForm($tokenModel);

        if ($model->load(Yii::$app->request->post()) && $model->resetPassword()) {
            Yii::$app->session->setFlash(
                'success',
                'Пароль успішно змінено. Тепер ви можете увійти до кабінету.'
            );

            return $this->redirect(['/customer/login']);
        }

        return $this->render('reset-password', [
            'model' => $model,
        ]);
    }

    public function actionLogout()
    {
        if (!Yii::$app->user->isGuest) {
            Yii::$app->user->logout();
        }

        return $this->goHome();
    }

    public function actionIndex()
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/customer/login']);
        }

        return $this->redirect([
            '/customer/view',
            'customerHash' => Yii::$app->user->identity->hash,
        ]);
    }

    public function actionView(string $customerHash)
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/customer/login']);
        }

        $customer = Yii::$app->user->identity;

        if ($customer->hash !== $customerHash) {
            throw new ForbiddenHttpException('Access denied.');
        }

        return $this->render('view', [
            'customer' => $customer,
        ]);
    }

    public function actionOrders(string $customerHash)
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/customer/login']);
        }

        $customer = Yii::$app->user->identity;

        if ($customer->hash !== $customerHash) {
            throw new ForbiddenHttpException('Access denied.');
        }

        return $this->render('orders', [
            'customer' => $customer,
            'orders' => [],
        ]);
    }

    protected function getRestoreJokes(): array
    {
        return [
            'Схоже, ви забули не лише пароль, а й те, що вже авторизовані 🙂',
            'Добрі новини: пароль згадувати не треба — ви вже в кабінеті.',
            'Невеличка плутанина: доступ відновлювати не треба, він у вас уже є 😄',
            'Здається, ви вирішили відновити те, що ще навіть не втрачали.',
            'Пароль, може, й забувся… але система пам’ятає, що ви вже всередині.',
            'Спроба відновити доступ успішно не потрібна: ви вже авторизовані.',
            'Це вже рівень майстра: шукати вхід, коли ви давно зайшли.',
            'Несподіваний сюжетний поворот: двері вже відчинені, а ви стоїте з ключем.',
            'Схоже, сьогодні не пароль забувся, а сам факт входу в кабінет.',
            'Відновлення скасовано через відсутність втрати: ви вже у своєму кабінеті.',
            'Пароль у безпеці. Кабінет теж. І ви вже там.',
            'Технічно ви намагаєтесь увійти туди, звідки ще не виходили.',
        ];
    }
}