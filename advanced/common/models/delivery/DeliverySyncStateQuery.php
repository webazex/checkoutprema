<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\enums\delivery\DeliverySyncScope;
use common\enums\delivery\DeliverySyncStatus;
use yii\db\ActiveQuery;

/**
 * @method DeliverySyncStateModel|null one($db = null)
 * @method DeliverySyncStateModel[] all($db = null)
 */
final class DeliverySyncStateQuery extends ActiveQuery
{
    public function byId(int $id): self
    {
        return $this->andWhere(['id' => $id]);
    }

    public function byProviderScope(
        int                      $providerId,
        DeliverySyncScope|string $scope,
        string                   $scopeExternalRef = ''
    ): self
    {
        return $this
            ->byProviderId($providerId)
            ->byScope($scope)
            ->byScopeExternalRef($scopeExternalRef);
    }

    public function byScopeExternalRef(string $scopeExternalRef): self
    {
        return $this->andWhere([
            'scope_external_ref' => $scopeExternalRef,
        ]);
    }

    public function byScope(DeliverySyncScope|string $scope): self
    {
        return $this->andWhere([
            'scope' => $scope instanceof DeliverySyncScope
                ? $scope->value
                : $scope,
        ]);
    }

    public function byProviderId(int $providerId): self
    {
        return $this->andWhere(['provider_id' => $providerId]);
    }

    public function byRunToken(string $runToken): self
    {
        return $this->andWhere(['run_token' => $runToken]);
    }

    public function failed(): self
    {
        return $this->byStatus(DeliverySyncStatus::FAILED);
    }

    public function byStatus(DeliverySyncStatus|string $status): self
    {
        return $this->andWhere([
            'status' => $status instanceof DeliverySyncStatus
                ? $status->value
                : $status,
        ]);
    }

    public function staleBefore(int $timestamp): self
    {
        return $this
            ->running()
            ->andWhere([
                'or',
                ['heartbeat_at' => null],
                ['<', 'heartbeat_at', $timestamp],
            ]);
    }

    public function running(): self
    {
        return $this->byStatus(DeliverySyncStatus::RUNNING);
    }

    public function orderedByLastSuccess(): self
    {
        return $this->orderBy([
            'last_success_at' => SORT_DESC,
            'id' => SORT_ASC,
        ]);
    }
}