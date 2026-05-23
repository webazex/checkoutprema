<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use common\models\Order;
use common\models\OrderItem;
use frontend\models\CheckoutForm;
use common\services\customer\CheckoutCustomerService;
use common\services\customer\CheckoutCustomerResult;
use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\product\ProductModel;
use DomainException;

class CartController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'add' => ['post'],
                    'update' => ['post'],
                    'remove' => ['post'],
                    'clear' => ['post'],
                    'checkout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * /cart
     * Если есть текущая draft-корзина — уводим в её защищённый URL.
     * Если нет — создаём новую draft-корзину и тоже уводим туда.
     */
    public function actionIndex(): Response
    {
        $order = $this->getCurrentOrder(true);

        return $this->redirect([
            '/cart/view',
            'customerHash' => $order->customer_hash,
            'orderId' => $order->id,
        ]);
    }

    /**
     * /cart/<customerHash>/<orderId>
     * Основная страница корзины.
     */
    public function actionView(string $customerHash, string|int $orderId): string
    {
        $order = $this->findActiveOrder($customerHash, $orderId);

        Yii::$app->session->set('customer_hash', $customerHash);
        Yii::$app->session->set('active_order_id', $order->id);

        $items = $order->getOrderItems()
            ->with([
                'product' => function ($query) {
                    $query->select(['id', 'title', 'slug', 'price', 'main_image']);
                }
            ])
            ->all();

        $isEmpty = count($items) === 0;

        return $this->render($isEmpty ? 'view-empty' : 'view', [
            'order' => $order,
            'items' => $items,
            'isEmpty' => $isEmpty,
            'total' => $order->calculateTotal(),
            'itemCount' => $order->getTotalQuantity(),
            'customerHash' => $customerHash,
            'orderId' => $orderId,
            'checkoutForm' => new CheckoutForm(),
        ]);
    }

    /**
     * Обработка checkout-формы.
     *
     * На этом этапе закрываем customer-flow:
     * - валидируем checkout-данные
     * - находим / создаём customer
     * - проверяем inactive / blocked
     * - при необходимости логиним
     *
     * Финальное создание order/order_item можно встроить следующим шагом,
     * когда синхронизируем cart persistence c утверждённой схемой БД.
     */
    public function actionCheckout(): Response
    {
        $form = new CheckoutForm();

        if (!$form->load(Yii::$app->request->post()) || !$form->validate()) {
            return $this->asJson([
                'success' => false,
                'message' => Yii::t('frontend', 'Please check the entered data.'),
                'errors' => $form->errors,
            ]);
        }

        try {
            $customerResult = (new CheckoutCustomerService())->process($form->getCustomerPayload());
        } catch (\Throwable $e) {
            Yii::error([
                'message' => 'Checkout customer processing failed.',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => Yii::t('frontend', 'An error occurred while processing the customer.'),
            ]);
        }

        if ($customerResult->status === CheckoutCustomerResult::STATUS_DENIED) {
            return $this->asJson([
                'success' => false,
                'message' => $customerResult->message,
                'code' => 'customer_denied',
            ]);
        }

        if (
            $customerResult->shouldLogin
            && Yii::$app->user->isGuest
            && $customerResult->customer !== null
        ) {
            Yii::$app->user->login($customerResult->customer, 3600 * 24 * 30);
        }

        // Временный этап:
        // сохраняем checkout payload в session, пока не зафиксирован
        // окончательный способ финализации order/order_item.
        Yii::$app->session->set('checkout_customer_id', $customerResult->customer?->id);
        Yii::$app->session->set('checkout_customer_email', $customerResult->customer?->email);
        Yii::$app->session->set('checkout_delivery_payload', $form->getDeliveryPayload());
        Yii::$app->session->set('checkout_payment_payload', $form->getPaymentPayload());

        return $this->asJson([
            'success' => true,
            'message' => Yii::t('frontend', 'Customer data processed successfully.'),
            'customerStatus' => $customerResult->status,
            'customerId' => $customerResult->customer?->id,
            'shouldLogin' => $customerResult->shouldLogin,
            'shouldSendPasswordSetupEmail' => $customerResult->shouldSendPasswordSetupEmail,
        ]);
    }

    /**
     * Добавление товара в корзину.
     * Ожидается POST:
     * - product_id
     * - qty
     */
    public function actionAdd(): Response
    {
        $request = Yii::$app->request;

        try {
            $productId = (int)$request->post('product_id');
            $qty = max(1, (int)$request->post('qty', 1));

            if ($productId < 1) {
                throw new DomainException('Невірний товар.');
            }

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

            $currency = strtoupper((string)($product->currency ?: 'UAH'));

            /** @var CartModel $cart */
            $cart = Yii::$app->db->transaction(function () use ($product, $qty, $currency): CartModel {
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

            $checkoutUrl = Yii::$app->urlManager->createUrl([
                '/checkout/view',
                'hash' => $cart->hash,
            ]);

            if (!$request->isAjax) {
                return $this->redirect($checkoutUrl);
            }

            return $this->asJson([
                'success' => true,
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
        } catch (\Throwable $e) {
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

    /**
     * Обновление количества товара.
     * Ожидается POST:
     * - item_id
     * - qty
     */
    public function actionUpdate(): Response
    {
        $itemId = (int)Yii::$app->request->post('item_id');
        $qty = max(1, (int)Yii::$app->request->post('qty', 1));

        if ($itemId < 1) {
            return $this->asJson([
                'success' => false,
                'message' => 'Невірний елемент кошика.',
            ]);
        }

        $item = OrderItem::findOne($itemId);
        if ($item === null) {
            return $this->asJson([
                'success' => false,
                'message' => 'Товар у кошику не знайдено.',
            ]);
        }

        $order = $this->findOrderByItem($item);

        $item->quantity = $qty;
        $item->price_total = (float)$item->price * (int)$item->quantity;

        if (!$item->save()) {
            return $this->asJson([
                'success' => false,
                'message' => 'Не вдалося оновити кількість товару.',
                'errors' => $item->errors,
            ]);
        }

        $this->touchOrder($order);

        return $this->asJson([
            'success' => true,
            'cartUrl' => $this->buildCartUrl($order),
            'itemCount' => $order->getTotalQuantity(),
            'totalAmount' => $order->calculateTotal(),
        ]);
    }

    /**
     * Удаление позиции из корзины.
     * Ожидается POST:
     * - item_id
     */
    public function actionRemove(): Response
    {
        $itemId = (int)Yii::$app->request->post('item_id');

        if ($itemId < 1) {
            return $this->asJson([
                'success' => false,
                'message' => 'Невірний елемент кошика.',
            ]);
        }

        $item = OrderItem::findOne($itemId);
        if ($item === null) {
            return $this->asJson([
                'success' => false,
                'message' => 'Товар у кошику не знайдено.',
            ]);
        }

        $order = $this->findOrderByItem($item);

        if ($item->delete() === false) {
            return $this->asJson([
                'success' => false,
                'message' => 'Не вдалося видалити товар із кошика.',
            ]);
        }

        $this->touchOrder($order);

        return $this->asJson([
            'success' => true,
            'cartUrl' => $this->buildCartUrl($order),
            'itemCount' => $order->getTotalQuantity(),
            'totalAmount' => $order->calculateTotal(),
            'isEmpty' => $order->getTotalQuantity() === 0,
        ]);
    }

    /**
     * Полная очистка текущей корзины.
     */
    public function actionClear(): Response
    {
        $order = $this->getCurrentOrder(false);

        if ($order === null) {
            return $this->asJson([
                'success' => true,
                'message' => 'Кошик уже порожній.',
            ]);
        }

        OrderItem::deleteAll(['order_id' => $order->id]);
        $this->touchOrder($order);

        return $this->asJson([
            'success' => true,
            'cartUrl' => $this->buildCartUrl($order),
            'itemCount' => 0,
            'totalAmount' => 0,
            'isEmpty' => true,
        ]);
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

    private function assertCartCurrency(CartModel $cart, string $currency): void
    {
        if (strtoupper((string)$cart->currency) !== strtoupper($currency)) {
            throw new DomainException('У кошику вже є товари в іншій валюті.');
        }
    }

    private function ensureSessionStarted(): void
    {
        if (!Yii::$app->session->isActive) {
            Yii::$app->session->open();
        }
    }

    private function formatModelErrors(array $errors): string
    {
        return json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'unknown error';
    }

    /**
     * Находит активную draft-корзину.
     */
    protected function findActiveOrder(string $customerHash, string|int $orderId): Order
    {
        $order = Order::findOne([
            'id' => $orderId,
            'customer_hash' => $customerHash,
            'status' => Order::STATUS_DRAFT,
        ]);

        if ($order === null) {
            throw new NotFoundHttpException('Кошик не знайдено або його вже оформлено.');
        }

        return $order;
    }

    /**
     * Возвращает текущую корзину из session.
     * При необходимости создаёт новую.
     */
    protected function getCurrentOrder(bool $createIfNotExists = false): ?Order
    {
        $customerHash = Yii::$app->session->get('customer_hash');
        $orderId = Yii::$app->session->get('active_order_id');

        if (!empty($customerHash) && !empty($orderId)) {
            $order = Order::findOne([
                'id' => $orderId,
                'customer_hash' => $customerHash,
                'status' => Order::STATUS_DRAFT,
            ]);

            if ($order !== null) {
                return $order;
            }
        }

        if (!$createIfNotExists) {
            return null;
        }

        $order = new Order();
        $order->customer_hash = Yii::$app->security->generateRandomString(32);
        $order->status = Order::STATUS_DRAFT;

        if ($order->hasAttribute('created_at')) {
            $order->created_at = time();
        }

        if ($order->hasAttribute('updated_at')) {
            $order->updated_at = time();
        }

        if (!$order->save()) {
            Yii::error([
                'message' => 'Не вдалося створити чернетку замовлення.',
                'errors' => $order->errors,
            ], __METHOD__);

            throw new \RuntimeException('Не вдалося створити кошик.');
        }

        Yii::$app->session->set('customer_hash', $order->customer_hash);
        Yii::$app->session->set('active_order_id', $order->id);

        return $order;
    }

    /**
     * Находит заказ по item.
     */
    protected function findOrderByItem(OrderItem $item): Order
    {
        $order = Order::findOne($item->order_id);

        if ($order === null || (int)$order->status !== (int)Order::STATUS_DRAFT) {
            throw new BadRequestHttpException('Кошик недоступний для редагування.');
        }

        return $order;
    }

    /**
     * Обновляет updated_at у заказа.
     */
    protected function touchOrder(Order $order): void
    {
        if ($order->hasAttribute('updated_at')) {
            $order->updated_at = time();
            $order->save(false, ['updated_at']);
        }
    }

    /**
     * Генерирует canonical cart URL.
     */
    protected function buildCartUrl(Order $order): string
    {
        return Yii::$app->urlManager->createUrl([
            '/cart/view',
            'customerHash' => $order->customer_hash,
            'orderId' => $order->id,
        ]);
    }

    /**
     * Временная заглушка.
     * Позже заменить на Product::findOne($productId) и актуальную цену.
     */
    protected function getProductPrice(int $productId): float
    {
        return 1499.00;
    }
}