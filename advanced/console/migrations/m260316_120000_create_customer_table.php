<?php
use yii\db\Migration;

class m260316_120000_create_customer_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%customer}}', [
            'id'            => $this->primaryKey(),
            'unic_hash_str' => $this->string(64)->unique()->notNull(),
            'email'         => $this->string(255)->unique(),
            'phone'         => $this->string(20),
            'first_name'    => $this->string(100),
            'last_name'     => $this->string(100),
            'password_hash' => $this->string(255),
            'auth_key'      => $this->string(32)->notNull(),
            'status'        => $this->smallInteger()->defaultValue(1),
            'created_at'    => $this->integer()->unsigned(),
            'updated_at'    => $this->integer()->unsigned(),
        ]);

        $this->createIndex('idx_customer_email', '{{%customer}}', 'email');
        $this->createIndex('idx_customer_hash',  '{{%customer}}', 'unic_hash_str', true);
    }

    public function down()
    {
        $this->dropTable('{{%customer}}');
    }
}