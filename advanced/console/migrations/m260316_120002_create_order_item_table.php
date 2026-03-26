<?php
use yii\db\Migration;

class m260316_120002_create_order_item_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%order_item}}', [
            'id'             => $this->primaryKey(),
            'order_id'       => $this->integer()->notNull(),
            'wix_product_id' => $this->string(50)->notNull(),
            'title'          => $this->string(255)->notNull(),
            'price'          => $this->decimal(10, 2)->notNull(),
            'quantity'       => $this->integer()->unsigned()->notNull(),
            'subtotal'       => $this->decimal(12, 2)->notNull(),
        ]);

        $this->createIndex('idx_order_item_order', '{{%order_item}}', 'order_id');

        $this->addForeignKey(
            'fk_order_item_order',
            '{{%order_item}}',
            'order_id',
            '{{%order}}',
            'id',
            'CASCADE'
        );
    }

    public function down()
    {
        $this->dropTable('{{%order_item}}');
    }
}