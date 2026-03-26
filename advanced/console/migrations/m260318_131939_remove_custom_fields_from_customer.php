<?php

use yii\db\Migration;

class m260318_131939_remove_custom_fields_from_customer extends Migration
{
    public function safeUp()
    {
        $this->dropIndex('idx_customer_external_id', '{{%customer}}');
        $this->dropColumn('{{%customer}}', 'external_id');
        $this->dropColumn('{{%customer}}', 'custom_fields');
    }

    public function safeDown()
    {
        $this->addColumn('{{%customer}}', 'external_id', $this->bigInteger()->unsigned()->null()->after('id')->comment('ID из KeyCRM'));
        $this->addColumn('{{%customer}}', 'custom_fields', $this->json()->null()->after('last_name')->comment('Произвольные поля из CRM'));
        $this->createIndex('idx_customer_external_id', '{{%customer}}', 'external_id', true);
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260318_131939_remove_custom_fields_from_customer cannot be reverted.\n";

        return false;
    }
    */
}
