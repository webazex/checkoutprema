<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%product}}`.
 */
class m260318_120422_create_product_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%product}}', [
            'id' => $this->primaryKey(),
            'external_id' => $this->bigInteger()->unsigned()->notNull()->unique()->comment('ID из KeyCRM'),
            'name' => $this->string(255)->notNull(),
            'slug' => $this->string(255)->null()->unique(),
            'sku' => $this->string(64)->null(),
            'barcode' => $this->string(64)->null(),
            'description' => $this->text()->null(),
            'price' => $this->decimal(12, 2)->notNull()->defaultValue(0),
            'purchased_price' => $this->decimal(12, 2)->null(),
            'quantity' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'weight_kg' => $this->decimal(10, 3)->null(),
            'length_mm' => $this->integer()->unsigned()->null(),
            'width_mm' => $this->integer()->unsigned()->null(),
            'height_mm' => $this->integer()->unsigned()->null(),
            'thumbnail_url' => $this->string(512)->null(),
            'category_external_id' => $this->integer()->unsigned()->null()->comment('category_id из KeyCRM'),
            'is_archived' => $this->boolean()->notNull()->defaultValue(false),
            'custom_fields' => $this->json()->null()->comment('Произвольные поля из KeyCRM'),
            'created_at' => $this->integer()->unsigned()->notNull(),
            'updated_at' => $this->integer()->unsigned()->notNull(),
        ]);

        $this->createIndex('idx_product_sku', '{{%product}}', 'sku');
        $this->createIndex('idx_product_external_id', '{{%product}}', 'external_id', true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%product}}');
    }
}
