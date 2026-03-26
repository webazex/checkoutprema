<?php

use yii\db\Migration;

class m260319_100000_alter_customer_table_add_hash_and_keycrm_id extends Migration
{
    public function safeUp()
    {
        // 1. Переименовываем unic_hash_str -> hash
        $this->renameColumn('{{%customer}}', 'unic_hash_str', 'hash');

        // На всякий случай переименуем индекс, если он существует
        $this->dropIndex('idx_customer_hash', '{{%customer}}');
        $this->createIndex('idx_customer_hash', '{{%customer}}', 'hash', true);

        // 2. Добавляем nullable внешний ID из KeyCRM
        $this->addColumn(
            '{{%customer}}',
            'keycrm_customer_id',
            $this->bigInteger()->unsigned()->null()->after('id')->comment('ID покупателя в KeyCRM')
        );

        $this->createIndex(
            'idx_customer_keycrm_customer_id',
            '{{%customer}}',
            'keycrm_customer_id',
            true
        );

        // 3. Делаем email обязательным
        $this->alterColumn(
            '{{%customer}}',
            'email',
            $this->string(255)->notNull()
        );
    }

    public function safeDown()
    {
        // Откатываем email обратно в nullable
        $this->alterColumn(
            '{{%customer}}',
            'email',
            $this->string(255)->null()
        );

        // Удаляем keycrm_customer_id
        $this->dropIndex('idx_customer_keycrm_customer_id', '{{%customer}}');
        $this->dropColumn('{{%customer}}', 'keycrm_customer_id');

        // Возвращаем hash -> unic_hash_str
        $this->dropIndex('idx_customer_hash', '{{%customer}}');
        $this->renameColumn('{{%customer}}', 'hash', 'unic_hash_str');
        $this->createIndex('idx_customer_hash', '{{%customer}}', 'unic_hash_str', true);
    }
}