<?php

use yii\db\Migration;

class m260326_120600_create_payment_table extends Migration
{
    private string $table = '{{%payment}}';

    public function safeUp(): void
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci';

        $this->createTable($this->table, [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->notNull(),
            'customer_id' => $this->integer()->null(),
            'provider' => $this->string(32)->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('new'),
            'amount' => $this->decimal(12, 2)->notNull(),
            'currency' => $this->string(3)->notNull()->defaultValue('UAH'),
            'payment_method' => $this->string(64)->null(),
            'external_id' => $this->string(100)->null(),
            'external_order_id' => $this->string(100)->null(),
            'idempotency_key' => $this->string(100)->null(),
            'redirect_url' => $this->string(1024)->null(),
            'error_message' => $this->text()->null(),
            'paid_at' => $this->integer()->unsigned()->null(),
            'failed_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_payment_order_id', $this->table, 'order_id');
        $this->createIndex('idx_payment_customer_id', $this->table, 'customer_id');
        $this->createIndex('idx_payment_status', $this->table, 'status');
        $this->createIndex('idx_payment_provider_status', $this->table, ['provider', 'status']);
        $this->createIndex('idx_payment_provider_external_id', $this->table, ['provider', 'external_id']);
        $this->createIndex('uq_payment_idempotency_key', $this->table, 'idempotency_key', true);

        $this->addForeignKey(
            'fk_payment_order_id',
            $this->table,
            'order_id',
            '{{%order}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_payment_customer_id',
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
        $this->dropForeignKey('fk_payment_customer_id', $this->table);
        $this->dropForeignKey('fk_payment_order_id', $this->table);

        $this->dropIndex('uq_payment_idempotency_key', $this->table);
        $this->dropIndex('idx_payment_provider_external_id', $this->table);
        $this->dropIndex('idx_payment_provider_status', $this->table);
        $this->dropIndex('idx_payment_status', $this->table);
        $this->dropIndex('idx_payment_customer_id', $this->table);
        $this->dropIndex('idx_payment_order_id', $this->table);

        $this->dropTable($this->table);
    }
}