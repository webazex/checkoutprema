<?php

declare(strict_types=1);

namespace frontend\controllers;

use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\product\ProductModel;
use DomainException;
use Throwable;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class CartController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'add' => ['post'],
                    'buy-now' => ['post'],
                ],
            ],
        ];
    }

    /**
     * /cart
     *
     * В новой схеме /cart — безопасный алиас:
     * - если есть активная cart/cart_item корзина — ведём в checkout;
     * - если корзины нет — ведём в каталог.
     */
    public function actionIndex(): Response
    {
        $cart = $this->findActiveCartFromSession();

        if ($cart instanceof CartModel) {
            return $this->redirect($this->getCheckoutUrl($cart));
        }

        return $this->redirect(['/catalog/index']);
    }

    private function findActiveCartFromSession(): ?CartModel
    {
        $this->ensureSessionStarted();

        $session = Yii::$app->session;
        $activeCartHash = (string)$session->get('active_cart_hash', '');

        if ($activeCartHash !== '') {
            /** @var CartModel|null $cart */
            $cart = CartModel::find()
                ->where([
                    'hash' => $activeCartHash,
                    'status' => CartModel::STATUS_ACTIVE,
                ])
                ->one();

            if ($cart instanceof CartModel) {
                return $cart;
            }
        }

        $sessionKey = (string)$session->id;

        /** @var CartModel|null $cart */
        $cart = CartModel::find()
            ->where([
                'session_key' => $sessionKey,
                'source_type' => CartModel::SOURCE_DIRECT,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($cart instanceof CartModel) {
            $session->set('active_cart_hash', (string)$cart->hash);

            return $cart;
        }

        return null;
    }

    private function ensureSessionStarted(): void
    {
        if (!Yii::$app->session->isActive) {
            Yii::$app->session->open();
        }
    }

    private function getCheckoutUrl(CartModel $cart): string
    {
        return Yii::$app->urlManager->createUrl([
            '/checkout/view',
            'hash' => $cart->hash,
        ]);
    }

    /**
     * Добавить в корзину.
     *
     * Поведение:
     * - AJAX: JSON + остаёмся на странице;
     * - non-AJAX fallback: flash + назад в каталог/на referrer.
     */
    public function actionAdd(): Response
    {
        $request = Yii::$app->request;

        try {
            $cart = $this->addProductToActiveCart(
                productId: (int)$request->post('product_id'),
                qty: max(1, (int)$request->post('qty', 1))
            );

            $message = 'Товар додано до кошика.';
            $checkoutUrl = $this->getCheckoutUrl($cart);

            if (!$request->isAjax) {
                Yii::$app->session->setFlash('success', $message);

                return $this->redirect($request->referrer ?: ['/catalog/index']);
            }

            return $this->asJson([
                'success' => true,
                'message' => $message,
                'cartHash' => (string)$cart->hash,
                'checkoutUrl' => $checkoutUrl,
                'itemsCount' => (int)$cart->items_count,
                'totalAmount' => (float)$cart->total_amount,
            ]);
        } catch (DomainException $e) {
            if (!$request->isAjax) {
                Yii::$app->session->setFlash('error', $e->getMessage());

                return $this->redirect($request->referrer ?: ['/catalog/index']);
            }

            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Yii::error([
                'message' => 'Failed to add catalog product to cart.',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);

            if (!$request->isAjax) {
                Yii::$app->session->setFlash('error', 'Не вдалося додати товар до кошика.');

                return $this->redirect($request->referrer ?: ['/catalog/index']);
            }

            return $this->asJson([
                'success' => false,
                'message' => 'Не вдалося додати товар до кошика.',
            ]);
        }
    }

    private function addProductToActiveCart(int $productId, int $qty): CartModel
    {
        if ($productId < 1) {
            throw new DomainException('Невірний товар.');
        }

        $product = $this->findProduct($productId);
        $currency = strtoupper((string)($product->currency ?: 'UAH'));

        return Yii::$app->db->transaction(function () use ($product, $qty, $currency): CartModel {
            $cart = $this->getOrCreateActiveCart($currency);

            /** @var CartItemModel|null $item */
            $item = CartItemModel::find()
                ->where([
                    'cart_id' => (int)$cart->id,
                    'product_id' => (int)$product->id,
                ])
                ->one();

            if (!$item instanceof CartItemModel) {
                $item = new CartItemModel();
                $item->cart_id = (int)$cart->id;
                $item->product_id = (int)$product->id;
                $item->quantity = 0;
            }

            $item->title = (string)$product->name;
            $item->sku_snapshot = $product->sku ?: null;
            $item->price = (float)$product->price;
            $item->currency = $currency;
            $item->quantity = (int)$item->quantity + $qty;
            $item->subtotal = round((float)$item->price * (int)$item->quantity, 2);

            if (!$item->save()) {
                throw new DomainException(
                    'Не вдалося додати товар до кошика: ' . $this->formatModelErrors($item->errors)
                );
            }

            $this->refreshCartTotals($cart);

            return $cart;
        });
    }

    private function findProduct(int $productId): ProductModel
    {
        /** @var ProductModel|null $product */
        $product = ProductModel::find()
            ->notArchived()
            ->byId($productId)
            ->one();

        if (!$product instanceof ProductModel) {
            throw new DomainException('Товар не знайдено.');
        }

        if (!$product->getIsAvailable()) {
            throw new DomainException('Товар недоступний для замовлення.');
        }

        return $product;
    }

    private function getOrCreateActiveCart(string $currency): CartModel
    {
        $this->ensureSessionStarted();

        $session = Yii::$app->session;
        $activeCartHash = (string)$session->get('active_cart_hash', '');

        if ($activeCartHash !== '') {
            /** @var CartModel|null $cart */
            $cart = CartModel::find()
                ->where([
                    'hash' => $activeCartHash,
                    'status' => CartModel::STATUS_ACTIVE,
                ])
                ->one();

            if ($cart instanceof CartModel) {
                $this->assertCartCurrency($cart, $currency);

                return $cart;
            }
        }

        $sessionKey = (string)$session->id;

        /** @var CartModel|null $cart */
        $cart = CartModel::find()
            ->where([
                'session_key' => $sessionKey,
                'source_type' => CartModel::SOURCE_DIRECT,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($cart instanceof CartModel) {
            $this->assertCartCurrency($cart, $currency);
            $session->set('active_cart_hash', (string)$cart->hash);

            return $cart;
        }

        $cart = new CartModel();
        $cart->hash = Yii::$app->security->generateRandomString(32);
        $cart->session_key = $sessionKey;
        $cart->status = CartModel::STATUS_ACTIVE;
        $cart->source_type = CartModel::SOURCE_DIRECT;
        $cart->currency = $currency;
        $cart->items_count = 0;
        $cart->subtotal_amount = 0.0;
        $cart->total_amount = 0.0;
        $cart->last_activity_at = time();

        if (!$cart->save()) {
            throw new DomainException(
                'Не вдалося створити кошик: ' . $this->formatModelErrors($cart->errors)
            );
        }

        $session->set('active_cart_hash', (string)$cart->hash);

        return $cart;
    }

    private function assertCartCurrency(CartModel $cart, string $currency): void
    {
        if (strtoupper((string)$cart->currency) !== strtoupper($currency)) {
            throw new DomainException('У кошику вже є товари в іншій валюті.');
        }
    }

    private function formatModelErrors(array $errors): string
    {
        return json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'unknown error';
    }

    private function refreshCartTotals(CartModel $cart): void
    {
        /** @var CartItemModel[] $items */
        $items = CartItemModel::find()
            ->where(['cart_id' => (int)$cart->id])
            ->all();

        $itemsCount = 0;
        $subtotalAmount = 0.0;

        foreach ($items as $item) {
            $itemsCount += (int)$item->quantity;
            $subtotalAmount += (float)$item->subtotal;
        }

        $cart->items_count = $itemsCount;
        $cart->subtotal_amount = round($subtotalAmount, 2);
        $cart->total_amount = round($subtotalAmount, 2);
        $cart->last_activity_at = time();

        if (!$cart->save()) {
            throw new DomainException(
                'Не вдалося оновити кошик: ' . $this->formatModelErrors($cart->errors)
            );
        }
    }

    /**
     * Купить сейчас.
     *
     * Поведение:
     * - добавляем товар в cart/cart_item;
     * - сразу ведём в /checkout/<hash>.
     */
    public function actionBuyNow(): Response
    {
        $request = Yii::$app->request;

        try {
            $cart = $this->addProductToActiveCart(
                productId: (int)$request->post('product_id'),
                qty: max(1, (int)$request->post('qty', 1))
            );

            $checkoutUrl = $this->getCheckoutUrl($cart);

            if ($request->isAjax) {
                return $this->asJson([
                    'success' => true,
                    'cartHash' => (string)$cart->hash,
                    'checkoutUrl' => $checkoutUrl,
                    'itemsCount' => (int)$cart->items_count,
                    'totalAmount' => (float)$cart->total_amount,
                ]);
            }

            return $this->redirect($checkoutUrl);
        } catch (DomainException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());

            if ($request->isAjax) {
                return $this->asJson([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            return $this->redirect($request->referrer ?: ['/catalog/index']);
        } catch (Throwable $e) {
            Yii::error([
                'message' => 'Failed to buy catalog product now.',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);

            Yii::$app->session->setFlash('error', 'Не вдалося перейти до оформлення.');

            if ($request->isAjax) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Не вдалося перейти до оформлення.',
                ]);
            }

            return $this->redirect($request->referrer ?: ['/catalog/index']);
        }
    }
}