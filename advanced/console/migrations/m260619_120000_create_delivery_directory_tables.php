<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260619_120000_create_delivery_directory_tables extends Migration
{
    private const TABLE_PROVIDER = '{{%delivery_provider}}';
    private const TABLE_AREA = '{{%delivery_area}}';
    private const TABLE_SETTLEMENT = '{{%delivery_settlement}}';
    private const TABLE_POINT_TYPE = '{{%delivery_point_type}}';
    private const TABLE_POINT = '{{%delivery_point}}';
    private const TABLE_POINT_SCHEDULE = '{{%delivery_point_schedule}}';
    private const TABLE_SYNC_STATE = '{{%delivery_sync_state}}';

    /**
     * @var list<string>
     */
    private array $createdTables = [];

    public function safeUp(): void
    {
        $this->assertSupportedDriver();
        $this->assertTablesDoNotExist();

        try {
            $this->createProviderTable();
            $this->createPointTypeTable();
            $this->createAreaTable();
            $this->createSettlementTable();
            $this->createPointTable();
            $this->createPointScheduleTable();
            $this->createSyncStateTable();
            $this->createCheckConstraints();
            $this->seedReferenceData();
        } catch (Throwable $exception) {
            $this->dropCreatedTables();

            throw $exception;
        }
    }

    public function safeDown(): void
    {
        $this->dropTableIfExists(self::TABLE_POINT_SCHEDULE);
        $this->dropTableIfExists(self::TABLE_POINT);
        $this->dropTableIfExists(self::TABLE_SETTLEMENT);
        $this->dropTableIfExists(self::TABLE_AREA);
        $this->dropTableIfExists(self::TABLE_SYNC_STATE);
        $this->dropTableIfExists(self::TABLE_POINT_TYPE);
        $this->dropTableIfExists(self::TABLE_PROVIDER);
    }

    private function createProviderTable(): void
    {
        $this->createTable(self::TABLE_PROVIDER, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'code' => $this->string(64)->notNull(),
            'name' => $this->string(255)->notNull(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_PROVIDER;

        $this->createIndex(
            'uq_delivery_provider_code',
            self::TABLE_PROVIDER,
            'code',
            true
        );

        $this->createIndex(
            'idx_delivery_provider_active_sort',
            self::TABLE_PROVIDER,
            ['is_active', 'sort_order']
        );
    }

    private function createPointTypeTable(): void
    {
        $this->createTable(self::TABLE_POINT_TYPE, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'code' => $this->string(64)->notNull(),
            'name' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_POINT_TYPE;

        $this->createIndex(
            'uq_delivery_point_type_code',
            self::TABLE_POINT_TYPE,
            'code',
            true
        );
    }

    private function createAreaTable(): void
    {
        $this->createTable(self::TABLE_AREA, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'provider_id' => $this->bigInteger()->unsigned()->notNull(),
            'external_ref' => $this->opaqueString(128),
            'name' => $this->string(255)->notNull(),
            'search_name' => $this->string(255)->notNull(),
            'metadata_json' => $this->json()->null(),
            'source_seen_at' => $this->integer()->unsigned()->notNull(),
            'synced_at' => $this->integer()->unsigned()->notNull(),
            'archived_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_AREA;

        $this->createIndex(
            'uq_delivery_area_provider_external',
            self::TABLE_AREA,
            ['provider_id', 'external_ref'],
            true
        );

        $this->createIndex(
            'uq_delivery_area_id_provider',
            self::TABLE_AREA,
            ['id', 'provider_id'],
            true
        );

        $this->createIndex(
            'idx_delivery_area_provider_archived_search',
            self::TABLE_AREA,
            ['provider_id', 'archived_at', 'search_name']
        );

        $this->addForeignKey(
            'fk_delivery_area_provider',
            self::TABLE_AREA,
            'provider_id',
            self::TABLE_PROVIDER,
            'id',
            'RESTRICT',
            'RESTRICT'
        );
    }

    private function createSettlementTable(): void
    {
        $this->createTable(self::TABLE_SETTLEMENT, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'provider_id' => $this->bigInteger()->unsigned()->notNull(),
            'area_id' => $this->bigInteger()->unsigned()->notNull(),
            'external_ref' => $this->opaqueString(128),
            'delivery_ref' => $this->opaqueString(128, true),
            'name' => $this->string(255)->notNull(),
            'search_name' => $this->string(255)->notNull(),
            'present' => $this->text()->null(),
            'settlement_type_code' => $this->string(32)->null(),
            'district_name' => $this->string(255)->null(),
            'latitude' => $this->decimal(10, 7)->null(),
            'longitude' => $this->decimal(10, 7)->null(),
            'metadata_json' => $this->json()->null(),
            'source_seen_at' => $this->integer()->unsigned()->notNull(),
            'synced_at' => $this->integer()->unsigned()->notNull(),
            'archived_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_SETTLEMENT;

        $this->createIndex(
            'uq_delivery_settlement_provider_external',
            self::TABLE_SETTLEMENT,
            ['provider_id', 'external_ref'],
            true
        );

        $this->createIndex(
            'uq_delivery_settlement_id_provider',
            self::TABLE_SETTLEMENT,
            ['id', 'provider_id'],
            true
        );

        $this->createIndex(
            'idx_delivery_settlement_area_provider',
            self::TABLE_SETTLEMENT,
            ['area_id', 'provider_id']
        );

        $this->createIndex(
            'idx_delivery_settlement_provider_delivery_ref',
            self::TABLE_SETTLEMENT,
            ['provider_id', 'delivery_ref']
        );

        $this->createIndex(
            'idx_delivery_settlement_provider_area_search',
            self::TABLE_SETTLEMENT,
            ['provider_id', 'area_id', 'archived_at', 'search_name']
        );

        $this->addForeignKey(
            'fk_delivery_settlement_provider',
            self::TABLE_SETTLEMENT,
            'provider_id',
            self::TABLE_PROVIDER,
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->addForeignKey(
            'fk_delivery_settlement_area_provider',
            self::TABLE_SETTLEMENT,
            ['area_id', 'provider_id'],
            self::TABLE_AREA,
            ['id', 'provider_id'],
            'RESTRICT',
            'RESTRICT'
        );
    }

    private function createPointTable(): void
    {
        $this->createTable(self::TABLE_POINT, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'provider_id' => $this->bigInteger()->unsigned()->notNull(),
            'settlement_id' => $this->bigInteger()->unsigned()->notNull(),
            'type_id' => $this->bigInteger()->unsigned()->notNull(),
            'external_ref' => $this->opaqueString(128),
            'external_type_ref' => $this->opaqueString(128, true),
            'number' => $this->string(32)->null(),
            'name' => $this->text()->null(),
            'description' => $this->text()->null(),
            'address' => $this->text()->notNull(),
            'search_text' => $this->text()->notNull(),
            'latitude' => $this->decimal(10, 7)->null(),
            'longitude' => $this->decimal(10, 7)->null(),
            'source_category' => $this->string(64)->null(),
            'source_status' => $this->string(64)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(false),
            'is_selectable' => $this->boolean()->notNull()->defaultValue(false),
            'metadata_json' => $this->json()->null(),
            'source_seen_at' => $this->integer()->unsigned()->notNull(),
            'synced_at' => $this->integer()->unsigned()->notNull(),
            'archived_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_POINT;

        $this->createIndex(
            'uq_delivery_point_provider_external',
            self::TABLE_POINT,
            ['provider_id', 'external_ref'],
            true
        );

        $this->createIndex(
            'uq_delivery_point_id_provider',
            self::TABLE_POINT,
            ['id', 'provider_id'],
            true
        );

        $this->createIndex(
            'idx_delivery_point_settlement_provider',
            self::TABLE_POINT,
            ['settlement_id', 'provider_id']
        );

        $this->createIndex(
            'idx_delivery_point_type_id',
            self::TABLE_POINT,
            'type_id'
        );

        $this->createIndex(
            'idx_delivery_point_number',
            self::TABLE_POINT,
            'number'
        );

        $this->createIndex(
            'idx_delivery_point_provider_settlement_selectable',
            self::TABLE_POINT,
            ['provider_id', 'settlement_id', 'archived_at', 'is_selectable', 'number']
        );

        $this->createIndex(
            'idx_delivery_point_provider_selectable_number',
            self::TABLE_POINT,
            ['provider_id', 'archived_at', 'is_selectable', 'number']
        );

        $this->createIndex(
            'idx_delivery_point_provider_geo',
            self::TABLE_POINT,
            ['provider_id', 'archived_at', 'is_selectable', 'latitude', 'longitude']
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point}} '
            . 'ADD FULLTEXT INDEX [[ft_delivery_point_search_text]] ([[search_text]])'
        );

        $this->addForeignKey(
            'fk_delivery_point_provider',
            self::TABLE_POINT,
            'provider_id',
            self::TABLE_PROVIDER,
            'id',
            'RESTRICT',
            'RESTRICT'
        );

        $this->addForeignKey(
            'fk_delivery_point_settlement_provider',
            self::TABLE_POINT,
            ['settlement_id', 'provider_id'],
            self::TABLE_SETTLEMENT,
            ['id', 'provider_id'],
            'RESTRICT',
            'RESTRICT'
        );

        $this->addForeignKey(
            'fk_delivery_point_type',
            self::TABLE_POINT,
            'type_id',
            self::TABLE_POINT_TYPE,
            'id',
            'RESTRICT',
            'RESTRICT'
        );
    }

    private function createPointScheduleTable(): void
    {
        $this->createTable(self::TABLE_POINT_SCHEDULE, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'delivery_point_id' => $this->bigInteger()->unsigned()->notNull(),
            'weekday' => $this->tinyInteger()->unsigned()->notNull(),
            'interval_no' => $this->smallInteger()->unsigned()->notNull()->defaultValue(1),
            'opens_at' => $this->time()->null(),
            'closes_at' => $this->time()->null(),
            'is_closed' => $this->boolean()->notNull()->defaultValue(false),
            'valid_from' => $this->date()->null(),
            'valid_to' => $this->date()->null(),
            'source_seen_at' => $this->integer()->unsigned()->notNull(),
            'synced_at' => $this->integer()->unsigned()->notNull(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_POINT_SCHEDULE;

        $this->execute(
            "ALTER TABLE {{%delivery_point_schedule}} "
            . "ADD COLUMN [[valid_from_key]] DATE "
            . "GENERATED ALWAYS AS (COALESCE([[valid_from]], '1000-01-01')) STORED "
            . "AFTER [[valid_to]]"
        );

        $this->createIndex(
            'uq_delivery_point_schedule_interval',
            self::TABLE_POINT_SCHEDULE,
            ['delivery_point_id', 'weekday', 'interval_no', 'valid_from_key'],
            true
        );

        $this->addForeignKey(
            'fk_delivery_point_schedule_point',
            self::TABLE_POINT_SCHEDULE,
            'delivery_point_id',
            self::TABLE_POINT,
            'id',
            'CASCADE',
            'RESTRICT'
        );
    }

    private function createSyncStateTable(): void
    {
        $this->createTable(self::TABLE_SYNC_STATE, [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'provider_id' => $this->bigInteger()->unsigned()->notNull(),
            'scope' => $this->string(64)->notNull(),
            'scope_external_ref' => $this->opaqueStringWithDefault(128, ''),
            'status' => $this->string(32)->notNull()->defaultValue('idle'),
            'run_token' => $this->opaqueString(64, true),
            'cursor' => $this->text()->null(),
            'started_at' => $this->integer()->unsigned()->null(),
            'heartbeat_at' => $this->integer()->unsigned()->null(),
            'finished_at' => $this->integer()->unsigned()->null(),
            'last_success_at' => $this->integer()->unsigned()->null(),
            'last_error_at' => $this->integer()->unsigned()->null(),
            'last_error_type' => $this->string(64)->null(),
            'last_error_code' => $this->string(128)->null(),
            'last_error_message' => $this->text()->null(),
            'source_total_count' => $this->integer()->unsigned()->null(),
            'processed_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'created_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'updated_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'archived_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $this->tableOptions());

        $this->createdTables[] = self::TABLE_SYNC_STATE;

        $this->createIndex(
            'uq_delivery_sync_state_scope',
            self::TABLE_SYNC_STATE,
            ['provider_id', 'scope', 'scope_external_ref'],
            true
        );

        $this->createIndex(
            'idx_delivery_sync_state_provider_status',
            self::TABLE_SYNC_STATE,
            ['provider_id', 'status']
        );

        $this->createIndex(
            'idx_delivery_sync_state_provider_success',
            self::TABLE_SYNC_STATE,
            ['provider_id', 'last_success_at']
        );

        $this->createIndex(
            'idx_delivery_sync_state_status_heartbeat',
            self::TABLE_SYNC_STATE,
            ['status', 'heartbeat_at']
        );

        $this->createIndex(
            'idx_delivery_sync_state_provider_run_token',
            self::TABLE_SYNC_STATE,
            ['provider_id', 'run_token']
        );

        $this->addForeignKey(
            'fk_delivery_sync_state_provider',
            self::TABLE_SYNC_STATE,
            'provider_id',
            self::TABLE_PROVIDER,
            'id',
            'RESTRICT',
            'RESTRICT'
        );
    }

    private function createCheckConstraints(): void
    {
        $this->execute(
            'ALTER TABLE {{%delivery_settlement}} '
            . 'ADD CONSTRAINT [[chk_delivery_settlement_coordinates]] CHECK ('
            . '([[latitude]] IS NULL AND [[longitude]] IS NULL) OR '
            . '([[latitude]] IS NOT NULL AND [[longitude]] IS NOT NULL '
            . 'AND [[latitude]] BETWEEN -90 AND 90 '
            . 'AND [[longitude]] BETWEEN -180 AND 180)'
            . ')'
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point}} '
            . 'ADD CONSTRAINT [[chk_delivery_point_coordinates]] CHECK ('
            . '([[latitude]] IS NULL AND [[longitude]] IS NULL) OR '
            . '([[latitude]] IS NOT NULL AND [[longitude]] IS NOT NULL '
            . 'AND [[latitude]] BETWEEN -90 AND 90 '
            . 'AND [[longitude]] BETWEEN -180 AND 180)'
            . ')'
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point}} '
            . 'ADD CONSTRAINT [[chk_delivery_point_selectable_active]] '
            . 'CHECK ([[is_selectable]] = 0 OR [[is_active]] = 1)'
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point_schedule}} '
            . 'ADD CONSTRAINT [[chk_delivery_point_schedule_weekday]] '
            . 'CHECK ([[weekday]] BETWEEN 1 AND 7)'
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point_schedule}} '
            . 'ADD CONSTRAINT [[chk_delivery_point_schedule_interval]] '
            . 'CHECK ([[interval_no]] >= 1)'
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point_schedule}} '
            . 'ADD CONSTRAINT [[chk_delivery_point_schedule_time]] CHECK ('
            . '([[is_closed]] = 1 AND [[opens_at]] IS NULL AND [[closes_at]] IS NULL) OR '
            . '([[is_closed]] = 0 AND [[opens_at]] IS NOT NULL AND [[closes_at]] IS NOT NULL)'
            . ')'
        );

        $this->execute(
            'ALTER TABLE {{%delivery_point_schedule}} '
            . 'ADD CONSTRAINT [[chk_delivery_point_schedule_validity]] CHECK ('
            . '[[valid_from]] IS NULL OR [[valid_to]] IS NULL OR [[valid_from]] <= [[valid_to]]'
            . ')'
        );
    }

    private function seedReferenceData(): void
    {
        $now = time();

        $this->insert(self::TABLE_PROVIDER, [
            'code' => 'nova_poshta',
            'name' => 'Нова пошта',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->batchInsert(
            self::TABLE_POINT_TYPE,
            ['code', 'name', 'sort_order', 'created_at', 'updated_at'],
            [
                ['post_office', 'Поштове відділення', 10, $now, $now],
                ['cargo_branch', 'Вантажне відділення', 20, $now, $now],
                ['parcel_locker', 'Поштомат', 30, $now, $now],
                ['pickup_point', 'Пункт видачі', 40, $now, $now],
                ['partner_point', 'Партнерський пункт', 50, $now, $now],
            ]
        );
    }

    private function assertSupportedDriver(): void
    {
        if ($this->db->driverName !== 'mysql') {
            throw new RuntimeException(
                'Delivery directory migration requires MySQL or MariaDB.'
            );
        }
    }

    private function assertTablesDoNotExist(): void
    {
        foreach ($this->allTables() as $table) {
            if ($this->tableExists($table)) {
                throw new RuntimeException(sprintf(
                    'Delivery table %s already exists. Resolve the partial migration before retrying.',
                    $table
                ));
            }
        }
    }

    private function dropCreatedTables(): void
    {
        foreach (array_reverse($this->createdTables) as $table) {
            $this->dropTableIfExists($table);
        }
    }

    private function dropTableIfExists(string $table): void
    {
        if ($this->tableExists($table)) {
            $this->dropTable($table);
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->db->schema->getTableSchema($table, true) !== null;
    }

    /**
     * @return list<string>
     */
    private function allTables(): array
    {
        return [
            self::TABLE_PROVIDER,
            self::TABLE_AREA,
            self::TABLE_SETTLEMENT,
            self::TABLE_POINT_TYPE,
            self::TABLE_POINT,
            self::TABLE_POINT_SCHEDULE,
            self::TABLE_SYNC_STATE,
        ];
    }

    private function tableOptions(): string
    {
        return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
    }

    private function opaqueString(int $length, bool $nullable = false): string
    {
        return sprintf(
            'VARCHAR(%d) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin %s',
            $length,
            $nullable ? 'NULL' : 'NOT NULL'
        );
    }

    private function opaqueStringWithDefault(int $length, string $default): string
    {
        return sprintf(
            "VARCHAR(%d) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '%s'",
            $length,
            str_replace("'", "''", $default)
        );
    }
}