<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%order_item}}`.
 */
class m260318_120501_create_order_item_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%order_item}}', [
            'id'                => $this->primaryKey(),
            'order_id'          => $this->integer()->notNull(),
            'product_id'        => $this->integer()->null()->comment('ссылка на нашу таблицу product'),
            'external_product_id' => $this->bigInteger()->unsigned()->null()->comment('ID товара из KeyCRM'),
            'wix_product_id'    => $this->string(50)->null()->comment('ID из Wix, если был'),
            'title'             => $this->string(255)->notNull(),
            'price'             => $this->decimal(10, 2)->notNull(),
            'quantity'          => $this->integer()->unsigned()->notNull(),
            'subtotal'          => $this->decimal(12, 2)->notNull(),
            'custom_fields'     => $this->json()->null()->comment('Метаданные позиции из CRM'),
        ]);

        $this->createIndex('idx_order_item_order', '{{%order_item}}', 'order_id');
        $this->createIndex('idx_order_item_ext_product', '{{%order_item}}', 'external_product_id');

        $this->addForeignKey(
            'fk_order_item_order',
            '{{%order_item}}',
            'order_id',
            '{{%order}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_order_item_product',
            '{{%order_item}}',
            'product_id',
            '{{%product}}',
            'id',
            'SET NULL'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_order_item_product', '{{%order_item}}');
        $this->dropForeignKey('fk_order_item_order', '{{%order_item}}');
        $this->dropTable('{{%order_item}}');
    }
}
