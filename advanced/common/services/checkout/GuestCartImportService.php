<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\product\ProductModel;
use DomainException;
use InvalidArgumentException;
use Throwable;
use Yii;
use yii\db\Connection;
use yii\db\Exception as DbException;

final class GuestCartImportService
{
    public function __construct(
        private readonly CartProductResolver $productResolver,
    )
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws DomainException
     * @throws DbException
     */
    public function import(array $payload): array
    {
        $normalized = $this->normalizePayload($payload);

        /** @var Connection $db */
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            $cart = $this->findOrCreateCart(
                sessionKey: $normalized['sessionKey'],
                sourceType: $normalized['sourceType'],
                currency: $normalized['currency'],
            );

            CartItemModel::deleteAll(['cart_id' => $cart->id]);

            $itemsCount = 0;
            $subtotalAmount = 0.0;
            $responseItems = [];

            foreach ($normalized['items'] as $row) {
                $resolved = $this->productResolver->resolve($row);

                /** @var ProductModel $product */
                $product = $resolved['product'];
                $quantity = (int)$resolved['quantity'];
                $price = (float)$product->price;
                $lineSubtotal = round($price * $quantity, 2);
                $lineCurrency = strtoupper((string)($product->currency ?: $normalized['currency']));

                if ($lineCurrency !== $normalized['currency']) {
                    throw new DomainException(sprintf(
                        'Currency mismatch for product "%d": expected "%s", got "%s".',
                        (int)$product->id,
                        $normalized['currency'],
                        $lineCurrency,
                    ));
                }

                $item = new CartItemModel();
                $item->cart_id = (int)$cart->id;
                $item->product_id = (int)$product->id;
                $item->title = (string)$product->name;
                $item->sku_snapshot = $resolved['skuSnapshot'];
                $item->price = $price;
                $item->quantity = $quantity;
                $item->subtotal = $lineSubtotal;
                $item->currency = $lineCurrency;

                if (!$item->save()) {
                    throw new DomainException(
                        'Failed to save cart item: ' . json_encode($item->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                    );
                }

                $itemsCount += $quantity;
                $subtotalAmount += $lineSubtotal;

                $responseItems[] = [
                    'cartItemId' => (int)$item->id,
                    'resolvedBy' => $resolved['resolvedBy'],
                    'productId' => (int)$product->id,
                    'externalSource' => $resolved['externalSource'],
                    'externalId' => $resolved['externalId'],
                    'title' => (string)$item->title,
                    'sku' => $item->sku_snapshot,
                    'price' => (float)$item->price,
                    'quantity' => (int)$item->quantity,
                    'subtotal' => (float)$item->subtotal,
                    'currency' => (string)$item->currency,
                ];
            }

            $cart->currency = $normalized['currency'];
            $cart->items_count = $itemsCount;
            $cart->subtotal_amount = round($subtotalAmount, 2);
            $cart->total_amount = round($subtotalAmount, 2);
            $cart->last_activity_at = time();

            if (!$cart->save()) {
                throw new DomainException(
                    'Failed to save cart: ' . json_encode($cart->getFirstErrors(), JSON_UNESCAPED_UNICODE)
                );
            }

            $transaction->commit();

            return [
                'cartId' => (int)$cart->id,
                'hash' => (string)$cart->hash,
                'sessionKey' => (string)$cart->session_key,
                'status' => (string)$cart->status,
                'sourceType' => (string)$cart->source_type,
                'currency' => (string)$cart->currency,
                'itemsCount' => (int)$cart->items_count,
                'subtotalAmount' => (float)$cart->subtotal_amount,
                'totalAmount' => (float)$cart->total_amount,
                'items' => $responseItems,
            ];
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function normalizePayload(array $payload): array
    {
        $sessionKey = trim((string)($payload['sessionKey'] ?? ''));
        if ($sessionKey === '') {
            throw new InvalidArgumentException('Field "sessionKey" is required.');
        }

        $currency = strtoupper(trim((string)($payload['currency'] ?? 'UAH')));
        if ($currency === '') {
            $currency = 'UAH';
        }

        $sourceType = strtolower(trim((string)($payload['sourceType'] ?? 'wix')));
        if ($sourceType === '') {
            $sourceType = 'wix';
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('Field "items" must be a non-empty array.');
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(sprintf('Item at index %d must be an object.', $index));
            }

            $quantity = (int)($item['quantity'] ?? 0);
            if ($quantity < 1) {
                throw new InvalidArgumentException(sprintf('Field "quantity" must be >= 1 for item %d.', $index));
            }

            $hasProductId = isset($item['productId']) && (int)$item['productId'] > 0;
            $hasExternal = trim((string)($item['externalSource'] ?? '')) !== ''
                && trim((string)($item['externalId'] ?? '')) !== '';

            if (!$hasProductId && !$hasExternal) {
                throw new InvalidArgumentException(sprintf(
                    'Item %d must contain either "productId", or both "externalSource" and "externalId".',
                    $index
                ));
            }
        }

        return [
            'sessionKey' => $sessionKey,
            'currency' => $currency,
            'sourceType' => $sourceType,
            'items' => $items,
        ];
    }

    private function findOrCreateCart(string $sessionKey, string $sourceType, string $currency): CartModel
    {
        $cart = CartModel::find()
            ->where([
                'session_key' => $sessionKey,
                'source_type' => $sourceType,
                'status' => CartModel::STATUS_ACTIVE,
            ])
            ->one();

        if ($cart instanceof CartModel) {
            return $cart;
        }

        $cart = new CartModel();
        $cart->hash = Yii::$app->security->generateRandomString(32);
        $cart->session_key = $sessionKey;
        $cart->status = CartModel::STATUS_ACTIVE;
        $cart->source_type = $sourceType;
        $cart->currency = $currency;
        $cart->items_count = 0;
        $cart->subtotal_amount = 0.0;
        $cart->total_amount = 0.0;
        $cart->last_activity_at = time();

        if (!$cart->save()) {
            throw new DomainException(
                'Failed to create cart: ' . json_encode($cart->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $cart;
    }
}