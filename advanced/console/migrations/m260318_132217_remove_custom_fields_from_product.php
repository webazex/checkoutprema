<?php

use yii\db\Migration;

class m260318_132217_remove_custom_fields_from_product extends Migration
{
    public function safeUp()
    {
        $this->dropIndex('idx_product_external_id', '{{%product}}');
        $this->dropColumn('{{%product}}', 'external_id');
        $this->dropColumn('{{%product}}', 'custom_fields');
    }

    public function safeDown()
    {
        $this->addColumn('{{%product}}', 'external_id', $this->bigInteger()->unsigned()->notNull()->unique()->after('id')->comment('ID из KeyCRM'));
        $this->addColumn('{{%product}}', 'custom_fields', $this->json()->null()->after('is_archived')->comment('Произвольные поля из CRM'));
        $this->createIndex('idx_product_external_id', '{{%product}}', 'external_id', true);
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260318_132217_remove_custom_fields_from_product cannot be reverted.\n";

        return false;
    }
    */
}
