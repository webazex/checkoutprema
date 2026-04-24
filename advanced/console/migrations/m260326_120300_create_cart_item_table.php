<?php

use yii\db\Migration;

class m260326_120300_create_cart_item_table extends Migration
{
    private string $table = '{{%cart_item}}';

    public function safeUp(): void
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci';

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'cart_id' => $this->integer()->notNull(),
            'product_id' => $this->integer()->null(),
            'title' => $this->string(255)->notNull(),
            'sku_snapshot' => $this->string(64)->null(),
            'price' => $this->decimal(12, 2)->notNull(),
            'quantity' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'subtotal' => $this->decimal(12, 2)->notNull(),
            'currency' => $this->string(3)->notNull()->defaultValue('UAH'),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_cart_item_cart_id', $this->table, 'cart_id');
        $this->createIndex('idx_cart_item_product_id', $this->table, 'product_id');
        $this->createIndex('idx_cart_item_sku_snapshot', $this->table, 'sku_snapshot');

        $this->addForeignKey(
            'fk_cart_item_cart_id',
            $this->table,
            'cart_id',
            '{{%cart}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_cart_item_product_id',
            $this->table,
            'product_id',
            '{{%product}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_cart_item_product_id', $this->table);
        $this->dropForeignKey('fk_cart_item_cart_id', $this->table);

        $this->dropIndex('idx_cart_item_sku_snapshot', $this->table);
        $this->dropIndex('idx_cart_item_product_id', $this->table);
        $this->dropIndex('idx_cart_item_cart_id', $this->table);

        $this->dropTable($this->table);
    }
}