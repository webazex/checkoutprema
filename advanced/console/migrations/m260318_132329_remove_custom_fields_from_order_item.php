<?php

use yii\db\Migration;

class m260318_132329_remove_custom_fields_from_order_item extends Migration
{
    public function safeUp()
    {
        $this->dropIndex('idx_order_item_ext_product', '{{%order_item}}');
        $this->dropColumn('{{%order_item}}', 'external_product_id');
        $this->dropColumn('{{%order_item}}', 'custom_fields');
    }

    public function safeDown()
    {
        $this->addColumn('{{%order_item}}', 'external_product_id', $this->bigInteger()->unsigned()->null()->after('wix_product_id')->comment('ID товара из KeyCRM'));
        $this->addColumn('{{%order_item}}', 'custom_fields', $this->json()->null()->after('subtotal')->comment('Метаданные позиции из CRM'));
        $this->createIndex('idx_order_item_ext_product', '{{%order_item}}', 'external_product_id');
    }
    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260318_132329_remove_custom_fields_from_order_item cannot be reverted.\n";

        return false;
    }
    */
}
