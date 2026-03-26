<?php
use yii\db\Migration;

class m260316_120001_create_order_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%order}}', [
            'id'                 => $this->primaryKey(),
            'customer_id'        => $this->integer()->null(),                    // NULL = гость
            'customer_hash'      => $this->string(64)->notNull(),
            'status'             => $this->smallInteger()->notNull()->defaultValue(0),
            'total_amount'       => $this->decimal(12, 2)->defaultValue(0.00),
            'wfp_order_id'       => $this->string(100)->null(),
            'wfp_transaction_id' => $this->string(100)->null(),
            'paid_at'            => $this->integer()->unsigned()->null(),
            'created_at'         => $this->integer()->unsigned(),
            'updated_at'         => $this->integer()->unsigned(),
        ]);

        $this->createIndex('idx_order_customer_hash', '{{%order}}', 'customer_hash');
        $this->createIndex('idx_order_status',        '{{%order}}', 'status');
        $this->createIndex('idx_order_wfp',           '{{%order}}', 'wfp_order_id');

        // связь с customer (если клиент авторизован)
        $this->addForeignKey('fk_order_customer', '{{%order}}', 'customer_id', '{{%customer}}', 'id', 'SET NULL');
    }

    public function down()
    {
        $this->dropTable('{{%order}}');
    }
}