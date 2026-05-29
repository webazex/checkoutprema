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
                    'update-item' => ['post'],
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

            Yii::$app->session->setFlash('success', Yii::t('frontend', 'Cart was cleared.'));
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', Yii::t('frontend', 'Failed to clear cart.'));
        }

        return $this->redirect(['checkout/view', 'hash' => $hash]);
    }

    public function actionRemoveItem(string $hash): Response
    {
        $itemId = (int)Yii::$app->request->post('itemId', 0);

        try {
            if ($itemId <= 0) {
                throw new DomainException(Yii::t('frontend', 'Invalid cart item id.'));
            }

            /** @var CheckoutCartManageService $service */
            $service = Yii::$container->get(CheckoutCartManageService::class);
            $service->removeItemByHashAndItemId($hash, $itemId);

            Yii::$app->session->setFlash('success', Yii::t('frontend', 'Item was removed from cart.'));
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', Yii::t('frontend', 'Failed to remove item from cart.'));
        }

        return $this->redirect(['checkout/view', 'hash' => $hash]);
    }

    public function actionUpdateItem(string $hash): Response
    {
        $request = Yii::$app->request;
        $itemId = (int)$request->post('itemId', 0);
        $quantity = (int)$request->post('quantity', 0);
        $isAjax = $request->isAjax;

        try {
            if ($itemId <= 0) {
                throw new DomainException(Yii::t('frontend', 'Invalid cart item id.'));
            }

            if ($quantity <= 0) {
                throw new DomainException(Yii::t('frontend', 'Invalid quantity.'));
            }

            /** @var CheckoutCartManageService $manageService */
            $manageService = Yii::$container->get(CheckoutCartManageService::class);
            $manageService->updateItemQuantityByHashAndItemId($hash, $itemId, $quantity);

            /** @var CheckoutCartViewService $viewService */
            $viewService = Yii::$container->get(CheckoutCartViewService::class);
            $cart = $viewService->getActiveCartByHash($hash)->toArray();

            if ($isAjax) {
                return $this->asJson([
                    'success' => true,
                    'message' => Yii::t('frontend', 'Cart was updated.'),
                    'cart' => $cart,
                ]);
            }

            Yii::$app->session->setFlash('success', Yii::t('frontend', 'Cart was updated.'));

            return $this->redirect(['checkout/view', 'hash' => $hash]);
        } catch (DomainException $e) {
            if ($isAjax) {
                Yii::$app->response->statusCode = 422;

                return $this->asJson([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            Yii::$app->session->setFlash('error', $e->getMessage());

            return $this->redirect(['checkout/view', 'hash' => $hash]);
        } catch (\Throwable $e) {
            if ($isAjax) {
                Yii::$app->response->statusCode = 500;

                return $this->asJson([
                    'success' => false,
                    'message' => Yii::t('frontend', 'Failed to update cart.'),
                ]);
            }

            Yii::$app->session->setFlash('error', Yii::t('frontend', 'Failed to update cart.'));

            return $this->redirect(['checkout/view', 'hash' => $hash]);
        }
    }

    public function actionSubmit(string $hash): Response|string
    {
        try {
            /** @var CheckoutCartManageService $cartManageService */
            $cartManageService = Yii::$container->get(CheckoutCartManageService::class);
            $stockChanges = $cartManageService->normalizeStockByHash($hash);

            if ($stockChanges !== []) {
                Yii::$app->session->setFlash(
                    'error',
                    Yii::t(
                        'frontend',
                        'Cart quantities were updated according to current stock. Please review your order.'
                    )
                );

                return $this->redirect(['checkout/view', 'hash' => $hash]);
            }

            $payload = Yii::$app->request->post();
            $payload['cartHash'] = $hash;

            $callbackUrl = Yii::$app->request->hostInfo . '/api/v1/callbacks/payments/' . PaymentModel::PROVIDER_WAYFORPAY;
            $defaultReturnUrl = Yii::$app->request->hostInfo . '/checkout/payment-return';

            /** @var CheckoutSubmitService $service */
            $service = Yii::$container->get(CheckoutSubmitService::class);

            $result = $service->submit(
                payload: $payload,
                callbackUrl: $callbackUrl,
                defaultReturnUrl: $defaultReturnUrl,
            );

            $nextAction = $result['payment']['nextAction'] ?? null;

            if (!$nextAction || ($nextAction['type'] ?? null) !== 'redirect_post') {
                throw new ServerErrorHttpException(Yii::t('frontend', 'Unsupported payment next action.'));
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
            'context' => $this->buildPaymentReturnContext(
                Yii::$app->request->get(),
                Yii::$app->request->post(),
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function buildPaymentReturnContext(array $query, array $post): array
    {
        $orderReference = trim((string)(
            $post['orderReference']
            ?? $query['orderReference']
            ?? $post['order_reference']
            ?? $query['order_reference']
            ?? ''
        ));

        if ($orderReference === '') {
            return [
                'type' => 'generic',
                'title' => Yii::t('frontend', 'Payment return'),
                'message' => Yii::t('frontend', 'This page is used to return from payment. If you have just completed a payment, confirmation may take a few minutes.'),
                'note' => Yii::t('frontend', 'If you opened this page directly, no active payment is attached to this view.'),
                'orderId' => null,
                'paymentStatus' => null,
            ];
        }

        /** @var PaymentModel|null $payment */
        $payment = PaymentModel::find()
            ->with('order')
            ->where([
                'provider' => PaymentModel::PROVIDER_WAYFORPAY,
                'external_order_id' => $orderReference,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if (!$payment instanceof PaymentModel) {
            return [
                'type' => 'generic',
                'title' => Yii::t('frontend', 'Payment return'),
                'message' => Yii::t('frontend', 'Payment confirmation may take a few minutes.'),
                'note' => Yii::t('frontend', 'If the payment was completed successfully, the order will be processed after confirmation.'),
                'orderId' => null,
                'paymentStatus' => null,
            ];
        }

        $orderId = $payment->order ? (int)$payment->order->id : null;

        if ($payment->status === PaymentModel::STATUS_PAID) {
            return [
                'type' => 'paid',
                'title' => Yii::t('frontend', 'Payment received'),
                'message' => Yii::t('frontend', 'Thank you. Your payment has been confirmed and the order is being processed.'),
                'note' => Yii::t('frontend', 'The manager will process your order soon.'),
                'orderId' => $orderId,
                'paymentStatus' => $this->getPaymentStatusLabel((string)$payment->status),
            ];
        }

        if ($payment->getIsFailed()) {
            return [
                'type' => 'failed',
                'title' => Yii::t('frontend', 'Payment was not completed'),
                'message' => Yii::t('frontend', 'The payment was not completed. Please try again or contact us.'),
                'note' => $payment->error_message ?: null,
                'orderId' => $orderId,
                'paymentStatus' => $this->getPaymentStatusLabel((string)$payment->status),
            ];
        }

        return [
            'type' => 'processing',
            'title' => Yii::t('frontend', 'Payment is being processed'),
            'message' => Yii::t('frontend', 'Thank you. Your payment has been accepted for processing.'),
            'note' => Yii::t('frontend', 'Payment confirmation may take a few minutes. After confirmation, your order will be transferred to the manager.'),
            'orderId' => $orderId,
            'paymentStatus' => $this->getPaymentStatusLabel((string)$payment->status),
        ];
    }

    private function getPaymentStatusLabel(?string $status): ?string
    {
        $status = trim((string)$status);

        if ($status === '') {
            return null;
        }

        return Yii::t('frontend', PaymentModel::getStatusLabelKey($status));
    }
}