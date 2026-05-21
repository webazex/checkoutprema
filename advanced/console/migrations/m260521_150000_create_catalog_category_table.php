<?php

use yii\db\Migration;

final class m260521_150000_create_catalog_category_table extends Migration
{
    private string $table = '{{%catalog_category}}';

    public function safeUp(): void
    {
        $tableOptions = null;

        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),

            'external_source' => $this->string(32)->notNull()->defaultValue('keycrm'),
            'external_id' => $this->string(100)->notNull(),

            'parent_id' => $this->integer()->null(),
            'parent_external_id' => $this->string(100)->null(),

            'name' => $this->string(255)->notNull(),
            'slug' => $this->string(255)->notNull(),

            'description' => $this->text()->null(),
            'thumbnail_url' => $this->string(512)->null(),

            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'is_archived' => $this->boolean()->notNull()->defaultValue(false),

            'seo_title' => $this->string(255)->null(),
            'seo_description' => $this->text()->null(),

            'raw_payload' => $this->json()->null(),

            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $tableOptions);

        $this->createIndex(
            'uq_catalog_category_external',
            $this->table,
            ['external_source', 'external_id'],
            true
        );

        $this->createIndex(
            'uq_catalog_category_slug',
            $this->table,
            'slug',
            true
        );

        $this->createIndex(
            'idx_catalog_category_parent_id',
            $this->table,
            'parent_id'
        );

        $this->createIndex(
            'idx_catalog_category_parent_external_id',
            $this->table,
            'parent_external_id'
        );

        $this->createIndex(
            'idx_catalog_category_active_sort',
            $this->table,
            ['is_active', 'sort_order']
        );

        $this->createIndex(
            'idx_catalog_category_archived',
            $this->table,
            ['is_archived']
        );

        $this->addForeignKey(
            'fk_catalog_category_parent_id',
            $this->table,
            'parent_id',
            $this->table,
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_category_parent_id', $this->table);

        $this->dropIndex('idx_catalog_category_archived', $this->table);
        $this->dropIndex('idx_catalog_category_active_sort', $this->table);
        $this->dropIndex('idx_catalog_category_parent_external_id', $this->table);
        $this->dropIndex('idx_catalog_category_parent_id', $this->table);
        $this->dropIndex('uq_catalog_category_slug', $this->table);
        $this->dropIndex('uq_catalog_category_external', $this->table);

        $this->dropTable($this->table);
    }
}