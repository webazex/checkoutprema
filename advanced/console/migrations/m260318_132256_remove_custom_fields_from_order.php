<?php

use yii\db\Migration;

class m260318_132256_remove_custom_fields_from_order extends Migration
{
    public function safeUp()
    {
        $this->dropIndex('idx_order_external_id', '{{%order}}');
        $this->dropColumn('{{%order}}', 'external_id');
        $this->dropColumn('{{%order}}', 'custom_fields');
    }

    public function safeDown()
    {
        $this->addColumn('{{%order}}', 'external_id', $this->bigInteger()->unsigned()->null()->unique()->after('payment_method')->comment('ID из KeyCRM или WayForPay'));
        $this->addColumn('{{%order}}', 'custom_fields', $this->json()->null()->after('external_id')->comment('Метаданные из CRM'));
        $this->createIndex('idx_order_external_id', '{{%order}}', 'external_id', true);
    }
    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260318_132256_remove_custom_fields_from_order cannot be reverted.\n";

        return false;
    }
    */
}
