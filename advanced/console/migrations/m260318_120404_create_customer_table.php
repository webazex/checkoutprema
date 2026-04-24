<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%customer}}`.
 */
class m260318_120404_create_customer_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%customer}}', [
            'id'            => $this->primaryKey(),
            'external_id'   => $this->bigInteger()->unsigned()->null()->unique()->comment('ID из KeyCRM'),
            'unic_hash_str' => $this->string(64)->unique()->notNull(),
            'email'         => $this->string(255)->unique()->null(),
            'phone'         => $this->string(30)->null(),
            'first_name'    => $this->string(100)->null(),
            'last_name'     => $this->string(100)->null(),
            'custom_fields' => $this->json()->null()->comment('Дополнительные поля из CRM'),
            'password_hash' => $this->string(255)->null(),
            'auth_key'      => $this->string(32)->notNull(),
            'status'        => $this->smallInteger()->notNull()->defaultValue(10), // 10 = active
            'created_at'    => $this->integer()->unsigned()->notNull(),
            'updated_at'    => $this->integer()->unsigned()->notNull(),
        ]);

        $this->createIndex('idx_customer_external_id', '{{%customer}}', 'external_id', true);
        $this->createIndex('idx_customer_hash', '{{%customer}}', 'unic_hash_str', true);
        $this->createIndex('idx_customer_email', '{{%customer}}', 'email');
    }

    public function safeDown()
    {
        $this->dropTable('{{%customer}}');
    }
}
