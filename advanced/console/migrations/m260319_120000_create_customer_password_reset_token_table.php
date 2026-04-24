<?php

use yii\db\Migration;

class m260319_120000_create_customer_password_reset_token_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%customer_password_reset_token}}', [
            'id' => $this->primaryKey(),
            'customer_id' => $this->integer()->notNull(),
            'token_hash' => $this->string(255)->notNull()->unique(),
            'expires_at' => $this->integer()->unsigned()->notNull(),
            'used_at' => $this->integer()->unsigned()->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->createIndex(
            'idx_customer_password_reset_token_customer_id',
            '{{%customer_password_reset_token}}',
            'customer_id'
        );

        $this->addForeignKey(
            'fk_customer_password_reset_token_customer_id',
            '{{%customer_password_reset_token}}',
            'customer_id',
            '{{%customer}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey(
            'fk_customer_password_reset_token_customer_id',
            '{{%customer_password_reset_token}}'
        );

        $this->dropTable('{{%customer_password_reset_token}}');
    }
}