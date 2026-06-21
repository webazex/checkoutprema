<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%order}}`.
 */
class m260318_120435_create_order_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%order}}', [
            'id' => $this->primaryKey(),
            'customer_id' => $this->integer()->notNull(),
            'total_amount' => $this->decimal(12, 2)->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('new'),     // new, paid, shipped, cancelled, ...
            'payment_status' => $this->string(32)->null(),                             // pending, success, failed, ...
            'payment_method' => $this->string(64)->null(),                             // wayforpay, cash, ...
            'external_id' => $this->bigInteger()->unsigned()->null()->unique()->comment('ID из KeyCRM или WayForPay'),
            'custom_fields' => $this->json()->null()->comment('Метаданные заказа из CRM'),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->createIndex('idx_order_customer', '{{%order}}', 'customer_id');
        $this->createIndex('idx_order_external_id', '{{%order}}', 'external_id', true);

        $this->addForeignKey(
            'fk_order_customer',
            '{{%order}}',
            'customer_id',
            '{{%customer}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_order_customer', '{{%order}}');
        $this->dropTable('{{%order}}');
    }
}
