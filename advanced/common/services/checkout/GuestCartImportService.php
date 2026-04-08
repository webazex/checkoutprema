<?php

declare(strict_types=1);

namespace common\services\checkout;

use common\models\cart\CartItemModel;
use common\models\cart\CartModel;
use common\models\product\ProductExternalMapModel;
use DomainException;
use InvalidArgumentException;
use Yii;
use yii\db\Connection;
use yii\db\Exception as DbException;

final class GuestCartImportService
{
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
                $map = ProductExternalMapModel::find()
                    ->bySourceAndExternalId($row['externalSource'], $row['externalId'])
                    ->with('product')
                    ->one();

                if ($map === null || $map->product === null) {
                    throw new DomainException(sprintf(
                        'Product mapping not found for source "%s" and external id "%s".',
                        $row['externalSource'],
                        $row['externalId'],
                    ));
                }

                $product = $map->product;
                $price = (float)$product->price;
                $quantity = $row['quantity'];
                $lineSubtotal = round($price * $quantity, 2);
                $lineCurrency = strtoupper((string)($product->currency ?: $normalized['currency']));

                if ($lineCurrency !== $normalized['currency']) {
                    throw new DomainException(sprintf(
                        'Currency mismatch for external id "%s": expected "%s", got "%s".',
                        $row['externalId'],
                        $normalized['currency'],
                        $lineCurrency,
                    ));
                }

                $item = new CartItemModel();
                $item->cart_id = (int)$cart->id;
                $item->product_id = (int)$product->id;
                $item->title = (string)$product->name;
                $item->sku_snapshot = $map->sku_snapshot ?: $product->sku;
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
                    'productId' => (int)$product->id,
                    'externalSource' => $row['externalSource'],
                    'externalId' => $row['externalId'],
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
        } catch (\Throwable $e) {
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

        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException('Field "items" must be a non-empty array.');
        }

        $normalizedItems = [];
        $sourceTypes = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(sprintf('Item at index %d must be an object.', $index));
            }

            $externalSource = strtolower(trim((string)($item['externalSource'] ?? '')));
            $externalId = trim((string)($item['externalId'] ?? ''));
            $quantity = (int)($item['quantity'] ?? 0);

            if ($externalSource === '') {
                throw new InvalidArgumentException(sprintf('Field "externalSource" is required for item %d.', $index));
            }

            if ($externalId === '') {
                throw new InvalidArgumentException(sprintf('Field "externalId" is required for item %d.', $index));
            }

            if ($quantity < 1) {
                throw new InvalidArgumentException(sprintf('Field "quantity" must be >= 1 for item %d.', $index));
            }

            $sourceTypes[$externalSource] = true;

            $normalizedItems[] = [
                'externalSource' => $externalSource,
                'externalId' => $externalId,
                'quantity' => $quantity,
            ];
        }

        if (count($sourceTypes) !== 1) {
            throw new InvalidArgumentException('Mixed external sources in one cart are not supported yet.');
        }

        $sourceType = array_key_first($sourceTypes);

        return [
            'sessionKey' => $sessionKey,
            'currency' => $currency,
            'sourceType' => $sourceType,
            'items' => $normalizedItems,
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