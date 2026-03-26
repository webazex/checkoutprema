<?php

use yii\db\Migration;

class m260326_120200_create_cart_table extends Migration
{
    private string $table = '{{%cart}}';

    public function safeUp(): void
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci';

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'hash' => $this->string(64)->notNull(),
            'customer_id' => $this->integer()->null(),
            'session_key' => $this->string(128)->null(),
            'status' => $this->string(32)->notNull()->defaultValue('active'),
            'source_type' => $this->string(32)->notNull()->defaultValue('direct'),
            'currency' => $this->string(3)->notNull()->defaultValue('UAH'),
            'items_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'subtotal_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0.00),
            'total_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0.00),
            'last_activity_at' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'expires_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $tableOptions);

        $this->createIndex('uq_cart_hash', $this->table, 'hash', true);
        $this->createIndex('idx_cart_customer_id', $this->table, 'customer_id');
        $this->createIndex('idx_cart_session_key', $this->table, 'session_key');
        $this->createIndex('idx_cart_status_last_activity_at', $this->table, ['status', 'last_activity_at']);

        $this->addForeignKey(
            'fk_cart_customer_id',
            $this->table,
            'customer_id',
            '{{%customer}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_cart_customer_id', $this->table);

        $this->dropIndex('idx_cart_status_last_activity_at', $this->table);
        $this->dropIndex('idx_cart_session_key', $this->table);
        $this->dropIndex('idx_cart_customer_id', $this->table);
        $this->dropIndex('uq_cart_hash', $this->table);

        $this->dropTable($this->table);
    }
}