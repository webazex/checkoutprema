<?php

use yii\db\Migration;

final class m260521_160100_alter_product_add_category_id extends Migration
{
    private string $productTable = '{{%product}}';
    private string $categoryTable = '{{%catalog_category}}';

    public function safeUp(): void
    {
        $this->addColumn(
            $this->productTable,
            'category_id',
            $this->integer()->null()->after('category_external_id')
        );

        $this->createIndex(
            'idx_product_category_id',
            $this->productTable,
            'category_id'
        );

        $this->addForeignKey(
            'fk_product_category_id',
            $this->productTable,
            'category_id',
            $this->categoryTable,
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_product_category_id', $this->productTable);
        $this->dropIndex('idx_product_category_id', $this->productTable);
        $this->dropColumn($this->productTable, 'category_id');
    }
}