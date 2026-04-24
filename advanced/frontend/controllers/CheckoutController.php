<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\payment\PaymentModel;
use common\services\cart\CheckoutCartViewService;
use common\services\checkout\CheckoutCartManageService;
use common\services\checkout\CheckoutSubmitService;
use DomainException;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

final class CheckoutController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'submit' => ['post'],
                    'clear' => ['post'],
                    'remove-item' => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if ($action->id === 'payment-return') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionView(string $hash): string
    {
        /** @var CheckoutCartViewService $service */
        $service = Yii::$container->get(CheckoutCartViewService::class);
        $cart = $service->getActiveCartByHash($hash);

        return $this->render('view', [
            'cart' => $cart->toArray(),
            'hash' => $hash,
        ]);
    }

    public function actionClear(string $hash): Response
    {
        try {
            /** @var CheckoutCartManageService $service */
            $service = Yii::$container->get(CheckoutCartManageService::class);
            $service->clearByHash($hash);

            Yii::$app->session->setFlash('success', 'Cart was cleared.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', 'Failed to clear cart.');
        }

        return $this->redirect(['checkout/view', 'hash' => $hash]);
    }

    public function actionRemoveItem(string $hash): Response
    {
        $itemId = (int)Yii::$app->request->post('itemId', 0);

        try {
            if ($itemId <= 0) {
                throw new DomainException('Invalid cart item id.');
            }

            /** @var CheckoutCartManageService $service */
            $service = Yii::$container->get(CheckoutCartManageService::class);
            $service->removeItemByHashAndItemId($hash, $itemId);

            Yii::$app->session->setFlash('success', 'Item was removed from cart.');
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', 'Failed to remove item from cart.');
        }

        return $this->redirect(['checkout/view', 'hash' => $hash]);
    }

    public function actionSubmit(string $hash): Response|string
    {
        $payload = Yii::$app->request->post();
        $payload['cartHash'] = $hash;

        $callbackUrl = Yii::$app->request->hostInfo . '/api/v1/callbacks/payments/' . PaymentModel::PROVIDER_WAYFORPAY;
        $defaultReturnUrl = Yii::$app->request->hostInfo . '/checkout/payment-return';

        try {
            /** @var CheckoutSubmitService $service */
            $service = Yii::$container->get(CheckoutSubmitService::class);

            $result = $service->submit(
                payload: $payload,
                callbackUrl: $callbackUrl,
                defaultReturnUrl: $defaultReturnUrl,
            );

            $nextAction = $result['payment']['nextAction'] ?? null;
            if (!$nextAction || ($nextAction['type'] ?? null) !== 'redirect_post') {
                throw new ServerErrorHttpException('Unsupported payment next action.');
            }

            return $this->render('payment_redirect', [
                'order' => $result['order'],
                'payment' => $result['payment'],
                'nextAction' => $nextAction,
            ]);
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());

            return $this->redirect(['checkout/view', 'hash' => $hash]);
        } catch (\Throwable $e) {
            throw new ServerErrorHttpException($e->getMessage(), 0, $e);
        }
    }

    public function actionPaymentReturn(): string
    {
        return $this->render('payment_return', [
            'query' => Yii::$app->request->get(),
            'post' => Yii::$app->request->post(),
            'method' => Yii::$app->request->method,
        ]);
    }
}