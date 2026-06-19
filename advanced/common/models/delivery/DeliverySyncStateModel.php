<?php

declare(strict_types=1);

namespace common\models\delivery;

use common\enums\delivery\DeliverySyncScope;
use common\enums\delivery\DeliverySyncStatus;
use common\models\BaseModel;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $provider_id
 * @property string $scope
 * @property string $scope_external_ref
 * @property string $status
 * @property string|null $run_token
 * @property string|null $cursor
 * @property int|null $started_at
 * @property int|null $heartbeat_at
 * @property int|null $finished_at
 * @property int|null $last_success_at
 * @property int|null $last_error_at
 * @property string|null $last_error_type
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property int|null $source_total_count
 * @property int $processed_count
 * @property int $created_count
 * @property int $updated_count
 * @property int $archived_count
 * @property int $created_at
 * @property int $updated_at
 *
 * @property DeliveryProviderModel $provider
 */
class DeliverySyncStateModel extends BaseModel
{
    public static function tableName(): string
    {
        return '{{%delivery_sync_state}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['provider_id', 'scope'], 'required'],

            [['scope_external_ref'], 'default', 'value' => ''],
            [
                ['status'],
                'default',
                'value' => DeliverySyncStatus::IDLE->value,
            ],

            [
                [
                    'processed_count',
                    'created_count',
                    'updated_count',
                    'archived_count',
                ],
                'default',
                'value' => 0,
            ],

            [
                [
                    'provider_id',
                    'started_at',
                    'heartbeat_at',
                    'finished_at',
                    'last_success_at',
                    'last_error_at',
                    'source_total_count',
                    'processed_count',
                    'created_count',
                    'updated_count',
                    'archived_count',
                    'created_at',
                    'updated_at',
                ],
                'integer',
                'min' => 0,
            ],

            [['cursor', 'last_error_message'], 'string'],

            [['scope', 'last_error_type'], 'string', 'max' => 64],
            [['scope_external_ref', 'last_error_code'], 'string', 'max' => 128],
            [['status'], 'string', 'max' => 32],
            [['run_token'], 'string', 'max' => 64],

            [
                ['scope'],
                'in',
                'range' => array_column(
                    DeliverySyncScope::cases(),
                    'value'
                ),
            ],

            [
                ['status'],
                'in',
                'range' => array_column(
                    DeliverySyncStatus::cases(),
                    'value'
                ),
            ],

            [
                ['scope_external_ref'],
                'unique',
                'targetAttribute' => [
                    'provider_id',
                    'scope',
                    'scope_external_ref',
                ],
            ],

            [
                ['provider_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => DeliveryProviderModel::class,
                'targetAttribute' => ['provider_id' => 'id'],
            ],
        ]);
    }

    public function getProvider(): ActiveQuery
    {
        return $this->hasOne(
            DeliveryProviderModel::class,
            ['id' => 'provider_id']
        );
    }

    public function getScopeEnum(): DeliverySyncScope
    {
        return DeliverySyncScope::from($this->scope);
    }

    public function getStatusEnum(): DeliverySyncStatus
    {
        return DeliverySyncStatus::from($this->status);
    }

    public function getIsRunning(): bool
    {
        return $this->status === DeliverySyncStatus::RUNNING->value;
    }

    public function getIsSucceeded(): bool
    {
        return $this->status === DeliverySyncStatus::SUCCEEDED->value;
    }

    public function getIsFailed(): bool
    {
        return $this->status === DeliverySyncStatus::FAILED->value;
    }

    public static function find(): DeliverySyncStateQuery
    {
        return new DeliverySyncStateQuery(static::class);
    }
}