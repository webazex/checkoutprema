<?php

use yii\db\Migration;

class m260326_120100_create_product_external_map_table extends Migration
{
    private string $table = '{{%product_external_map}}';

    public function safeUp(): void
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci';

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'external_source' => $this->string(32)->notNull()->comment('wix, prom, rozetka, etc.'),
            'external_id' => $this->string(100)->notNull()->comment('ID товара во внешнем канале'),
            'sku_snapshot' => $this->string(64)->null()->comment('SKU на момент привязки'),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $tableOptions);

        $this->createIndex(
            'uq_product_external_map_source_external_id',
            $this->table,
            ['external_source', 'external_id'],
            true
        );

        $this->createIndex(
            'uq_product_external_map_source_product_id',
            $this->table,
            ['external_source', 'product_id'],
            true
        );

        $this->createIndex(
            'idx_product_external_map_product_id',
            $this->table,
            'product_id'
        );

        $this->createIndex(
            'idx_product_external_map_sku_snapshot',
            $this->table,
            'sku_snapshot'
        );

        $this->addForeignKey(
            'fk_product_external_map_product_id',
            $this->table,
            'product_id',
            '{{%product}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_product_external_map_product_id', $this->table);

        $this->dropIndex('idx_product_external_map_sku_snapshot', $this->table);
        $this->dropIndex('idx_product_external_map_product_id', $this->table);
        $this->dropIndex('uq_product_external_map_source_product_id', $this->table);
        $this->dropIndex('uq_product_external_map_source_external_id', $this->table);

        $this->dropTable($this->table);
    }
}