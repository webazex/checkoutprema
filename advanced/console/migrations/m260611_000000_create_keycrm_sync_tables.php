<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260611_000000_create_keycrm_sync_tables extends Migration
{
    private const TABLE_RUN = '{{%keycrm_sync_run}}';
    private const TABLE_STATE = '{{%keycrm_sync_state}}';

    public function safeUp(): void
    {
        $this->createTable(self::TABLE_RUN, [
            'id' => $this->primaryKey(),

            'sync_type' => $this->string(64)->notNull(),
            'status' => $this->string(32)->notNull(),

            'unique_key' => $this->string(128)->null(),
            'queue_job_id' => $this->string(64)->null(),

            'params_json' => $this->text()->null(),
            'stats_json' => $this->text()->null(),

            'error_message' => $this->text()->null(),
            'error_trace' => $this->text()->null(),

            'queued_at' => $this->integer()->null(),
            'started_at' => $this->integer()->null(),
            'finished_at' => $this->integer()->null(),

            'queued_at_ms' => $this->bigInteger()->null(),
            'started_at_ms' => $this->bigInteger()->null(),
            'finished_at_ms' => $this->bigInteger()->null(),
            'duration_ms' => $this->integer()->null(),

            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex(
            'idx-keycrm_sync_run-type-status',
            self::TABLE_RUN,
            ['sync_type', 'status']
        );

        $this->createIndex(
            'idx-keycrm_sync_run-unique_key',
            self::TABLE_RUN,
            'unique_key'
        );

        $this->createIndex(
            'idx-keycrm_sync_run-queue_job_id',
            self::TABLE_RUN,
            'queue_job_id'
        );

        $this->createIndex(
            'idx-keycrm_sync_run-created_at',
            self::TABLE_RUN,
            'created_at'
        );

        $this->createTable(self::TABLE_STATE, [
            'id' => $this->primaryKey(),

            'sync_type' => $this->string(64)->notNull()->unique(),
            'status' => $this->string(32)->notNull(),

            'active_run_id' => $this->integer()->null(),
            'last_run_id' => $this->integer()->null(),

            'last_unique_key' => $this->string(128)->null(),
            'last_queue_job_id' => $this->string(64)->null(),

            'last_queued_at' => $this->integer()->null(),
            'last_started_at' => $this->integer()->null(),
            'last_finished_at' => $this->integer()->null(),
            'last_success_at' => $this->integer()->null(),
            'last_error_at' => $this->integer()->null(),

            'last_duration_ms' => $this->integer()->null(),
            'last_stats_json' => $this->text()->null(),
            'last_error_message' => $this->text()->null(),

            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk-keycrm_sync_state-active_run_id',
            self::TABLE_STATE,
            'active_run_id',
            self::TABLE_RUN,
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-keycrm_sync_state-last_run_id',
            self::TABLE_STATE,
            'last_run_id',
            self::TABLE_RUN,
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-keycrm_sync_state-last_run_id', self::TABLE_STATE);
        $this->dropForeignKey('fk-keycrm_sync_state-active_run_id', self::TABLE_STATE);

        $this->dropTable(self::TABLE_STATE);
        $this->dropTable(self::TABLE_RUN);
    }
}