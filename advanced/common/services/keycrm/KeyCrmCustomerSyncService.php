<?php

declare(strict_types=1);

namespace common\services\keycrm;

use common\integrations\keycrm\KeyCrmApiClient;
use common\models\customer\CustomerModel;
use DomainException;
use RuntimeException;
use yii\helpers\ArrayHelper;

final class KeyCrmCustomerSyncService
{
    public function __construct(
        private readonly KeyCrmApiClient $apiClient,
    ) {
    }

    public function sync(CustomerModel $customer): CustomerModel
    {
        if (!$customer->id) {
            throw new DomainException('Customer must be saved locally before KeyCRM sync.');
        }

        if ($this->hasKeyCrmId($customer)) {
            return $customer;
        }

        $remoteCustomer = $this->findRemoteCustomer($customer);

        if ($remoteCustomer === null) {
            $remoteCustomer = $this->createRemoteCustomer($customer);
        }

        $remoteId = $this->extractRemoteCustomerId($remoteCustomer);

        if ($remoteId === null || $remoteId === '') {
            throw new RuntimeException('KeyCRM customer ID was not returned.');
        }

        $customer->keycrm_customer_id = $remoteId;

        if (!$customer->save(true, ['keycrm_customer_id', 'updated_at'])) {
            throw new DomainException(
                'Failed to save keycrm_customer_id for local customer: ' .
                json_encode($customer->getFirstErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $customer;
    }

    private function hasKeyCrmId(CustomerModel $customer): bool
    {
        return is_string($customer->keycrm_customer_id)
            && trim($customer->keycrm_customer_id) !== '';
    }

    private function findRemoteCustomer(CustomerModel $customer): ?array
    {
        $email = $this->nullableString($customer->email);
        $phone = $this->nullableString($customer->phone);

        if ($email === null && $phone === null) {
            throw new DomainException('Customer email or phone is required for KeyCRM lookup.');
        }

        /**
         * TODO:
         * Подтвердить точный endpoint и query params по Swagger KeyCRM.
         *
         * Идея текущего каркаса:
         * - сначала ищем по email
         * - если не нашли, ищем по phone
         */
        if ($email !== null) {
            $response = $this->apiClient->get('/buyer', [
                'email' => $email,
                'limit' => 1,
            ]);

            $found = $this->extractFirstCustomer($response);
            if ($found !== null) {
                return $found;
            }
        }

        if ($phone !== null) {
            $response = $this->apiClient->get('/buyer', [
                'phone' => $phone,
                'limit' => 1,
            ]);

            $found = $this->extractFirstCustomer($response);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function createRemoteCustomer(CustomerModel $customer): array
    {
        /**
         * TODO:
         * Подтвердить точный create endpoint и payload schema по Swagger KeyCRM.
         */
        $payload = [
            'full_name' => $customer->getFullName() ?: $customer->email,
            'first_name' => $this->nullableString($customer->first_name),
            'last_name' => $this->nullableString($customer->last_name),
            'email' => $this->nullableString($customer->email),
            'phone' => $this->nullableString($customer->phone),
        ];

        $response = $this->apiClient->post('/buyer', $payload);

        if (!is_array($response)) {
            throw new RuntimeException('KeyCRM create customer returned invalid response.');
        }

        return $response;
    }

    private function extractFirstCustomer(array $response): ?array
    {
        /**
         * TODO:
         * После проверки Swagger привести к точной структуре ответа.
         *
         * Сейчас каркас пытается жить с самыми типичными формами:
         * - ['data' => [...]]
         * - ['data' => ['items' => [...]]]
         * - ['items' => [...]]
         */
        $candidates = null;

        if (isset($response['data']) && is_array($response['data'])) {
            if (array_is_list($response['data'])) {
                $candidates = $response['data'];
            } elseif (isset($response['data']['items']) && is_array($response['data']['items'])) {
                $candidates = $response['data']['items'];
            }
        }

        if ($candidates === null && isset($response['items']) && is_array($response['items'])) {
            $candidates = $response['items'];
        }

        if (!is_array($candidates) || $candidates === []) {
            return null;
        }

        $first = reset($candidates);

        return is_array($first) ? $first : null;
    }

    private function extractRemoteCustomerId(array $response): ?string
    {
        /**
         * TODO:
         * После проверки Swagger оставить один канонический путь.
         */
        $id = ArrayHelper::getValue($response, 'id')
            ?? ArrayHelper::getValue($response, 'data.id')
            ?? ArrayHelper::getValue($response, 'data.customer.id');

        if ($id === null) {
            return null;
        }

        $id = trim((string)$id);

        return $id !== '' ? $id : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}