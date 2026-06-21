<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%meta}}`.
 */
class m260318_133105_create_meta_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%meta}}', [
            'id' => $this->primaryKey(),
            'entity_type' => $this->string(32)->notNull()->comment('customer, product, order, order_item, payment'),
            'entity_id' => $this->bigInteger()->unsigned()->notNull(),
            'key' => $this->string(128)->notNull(),
            'value' => $this->text()->null(),
            'value_int' => $this->bigInteger()->null()->comment('для быстрого поиска чисел'),
            'value_decimal' => $this->decimal(15, 4)->null(),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        // Уникальность по сущности + ключ
        $this->createIndex('idx_meta_entity_key', '{{%meta}}', ['entity_type', 'entity_id', 'key'], true);

        // Индексы для поиска
        $this->createIndex('idx_meta_entity', '{{%meta}}', ['entity_type', 'entity_id']);
        $this->createIndex('idx_meta_value_int', '{{%meta}}', 'value_int');
    }

    public function safeDown()
    {
        $this->dropTable('{{%meta}}');
    }
}
