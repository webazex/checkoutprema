<?php

declare(strict_types=1);

namespace common\storages\delivery;

use common\dto\delivery\DeliverySyncStateReadDto;
use common\enums\delivery\DeliverySyncScope;
use common\mappers\delivery\DeliveryReadMapper;
use common\models\delivery\DeliverySyncStateModel;
use RuntimeException;
use yii\db\IntegrityException;

final readonly class DeliverySyncStateStorage
{
    public function __construct(
        private DeliveryProviderStorage $providers,
        private DeliveryReadMapper $mapper,
    ) {
    }

    public function find(
        string $providerCode,
        DeliverySyncScope $scope,
        string $scopeExternalRef = ''
    ): ?DeliverySyncStateReadDto {
        $provider = $this->providers->getByCode($providerCode);

        $model = $this->findModel(
            providerId: $provider->id,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            withProvider: true,
        );

        return $model === null
            ? null
            : $this->mapper->mapSyncState($model);
    }

    public function getOrCreate(
        string $providerCode,
        DeliverySyncScope $scope,
        string $scopeExternalRef = ''
    ): DeliverySyncStateReadDto {
        $provider = $this->providers->getByCode($providerCode);

        $model = $this->findModel(
            providerId: $provider->id,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            withProvider: true,
        );

        if ($model !== null) {
            return $this->mapper->mapSyncState($model);
        }

        $model = new DeliverySyncStateModel([
            'provider_id' => $provider->id,
            'scope' => $scope->value,
            'scope_external_ref' => $scopeExternalRef,
        ]);

        try {
            $this->saveModel($model);
        } catch (IntegrityException|RuntimeException $exception) {
            $model = $this->findModel(
                providerId: $provider->id,
                scope: $scope,
                scopeExternalRef: $scopeExternalRef,
                withProvider: true,
            );

            if ($model === null) {
                throw $exception;
            }

            return $this->mapper->mapSyncState($model);
        }

        $model = $this->findModel(
            providerId: $provider->id,
            scope: $scope,
            scopeExternalRef: $scopeExternalRef,
            withProvider: true,
        );

        if ($model === null) {
            throw new RuntimeException(sprintf(
                'Delivery sync state "%s:%s:%s" was created but could not be reloaded.',
                $providerCode,
                $scope->value,
                $scopeExternalRef
            ));
        }

        return $this->mapper->mapSyncState($model);
    }

    private function findModel(
        int $providerId,
        DeliverySyncScope $scope,
        string $scopeExternalRef,
        bool $withProvider
    ): ?DeliverySyncStateModel {
        $query = DeliverySyncStateModel::find()
            ->byProviderScope(
                $providerId,
                $scope,
                $scopeExternalRef
            );

        if ($withProvider) {
            $query->with('provider');
        }

        return $query->one();
    }

    private function saveModel(
        DeliverySyncStateModel $model
    ): void {
        if ($model->save()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Unable to save delivery sync state: %s',
            $this->formatErrors($model)
        ));
    }

    private function formatErrors(
        DeliverySyncStateModel $model
    ): string {
        $encoded = json_encode(
            $model->getErrors(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $encoded === false
            ? 'unknown validation error'
            : $encoded;
    }
}