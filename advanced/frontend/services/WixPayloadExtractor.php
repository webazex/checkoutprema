<?php

namespace frontend\services;

use yii\web\Request;
use common\dto\WixCartPayloadDto;

/**
 * Извлекает и нормализует входящие данные из request.
 *
 * Пока логика мягкая, потому что формат Wix не зафиксирован.
 */
class WixPayloadExtractor
{
    public function extract(Request $request): WixCartPayloadDto
    {
        $dto = new WixCartPayloadDto();

        $body = $request->bodyParams;
        $query = $request->queryParams;

        $dto->rawPayload = [
            'query' => $query,
            'body' => $body,
            'method' => $request->method,
            'url' => $request->absoluteUrl,
        ];

        // Базовые candidate-поля
        $dto->email = $this->firstNonEmpty([
            $body['email'] ?? null,
            $body['customer']['email'] ?? null,
            $query['email'] ?? null,
        ]);

        $dto->phone = $this->firstNonEmpty([
            $body['phone'] ?? null,
            $body['customer']['phone'] ?? null,
            $query['phone'] ?? null,
        ]);

        $dto->externalVisitorId = $this->firstNonEmpty([
            $body['visitor_id'] ?? null,
            $body['external_visitor_id'] ?? null,
            $query['visitor_id'] ?? null,
            $query['external_visitor_id'] ?? null,
        ]);

        $dto->customerHash = $this->firstNonEmpty([
            $body['customer_hash'] ?? null,
            $query['customer_hash'] ?? null,
        ]);

        // Пытаемся вытащить позиции корзины
        $rawItems = $this->extractRawItems($body, $query);

        foreach ($rawItems as $rawItem) {
            $normalized = $this->normalizeItem($rawItem);
            if ($normalized !== null) {
                $dto->items[] = $normalized;
            }
        }

        return $dto;
    }

    protected function extractRawItems(array $body, array $query): array
    {
        $candidates = [
            $body['items'] ?? null,
            $body['cartItems'] ?? null,
            $body['cart']['items'] ?? null,
            $query['items'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    protected function normalizeItem(mixed $rawItem): ?array
    {
        if (!is_array($rawItem)) {
            return null;
        }

        $productId = $this->normalizeProductId(
            $rawItem['product_id'] ?? null,
            $rawItem['productId'] ?? null,
            $rawItem['id'] ?? null
        );

        if ($productId === null) {
            return null;
        }

        $qty = $this->normalizeQty(
            $rawItem['qty'] ?? null,
            $rawItem['quantity'] ?? null,
            $rawItem['count'] ?? null
        );

        return [
            'productId' => $productId,
            'qty' => $qty,
            'raw' => $rawItem,
        ];
    }

    protected function normalizeProductId(mixed ...$values): ?int
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $productId = (int)$value;
            if ($productId > 0) {
                return $productId;
            }
        }

        return null;
    }

    protected function normalizeQty(mixed ...$values): int
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $qty = (int)$value;
            return max(1, $qty);
        }

        return 1;
    }

    protected function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}