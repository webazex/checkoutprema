<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%payment_log}}`.
 */
class m260318_120513_create_payment_log_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%payment_log}}', [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->notNull(),
            'external_id' => $this->string(100)->null()->comment('invoice / transaction ID от WayForPay'),
            'status' => $this->string(32)->notNull(),          // created, pending, approved, declined, refunded, ...
            'amount' => $this->decimal(12, 2)->notNull(),
            'currency' => $this->string(3)->notNull()->defaultValue('UAH'),
            'payment_method' => $this->string(64)->null(),
            'response_data' => $this->json()->null()->comment('Полный ответ от WayForPay / KeyCRM'),
            'error_message' => $this->text()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->createIndex('idx_payment_log_order', '{{%payment_log}}', 'order_id');
        $this->createIndex('idx_payment_log_external', '{{%payment_log}}', 'external_id');

        $this->addForeignKey(
            'fk_payment_log_order',
            '{{%payment_log}}',
            'order_id',
            '{{%order}}',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_payment_log_order', '{{%payment_log}}');
        $this->dropTable('{{%payment_log}}');
    }
}
