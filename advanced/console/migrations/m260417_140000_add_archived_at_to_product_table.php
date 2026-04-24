<?php


use yii\db\Migration;

final class m260417_140000_add_archived_at_to_product_table extends Migration
{
    public function safeUp(): void
    {
        $table = '{{%product}}';

        $this->addColumn(
            $table,
            'archived_at',
            $this->integer()->null()->after('is_archived')
        );

        $this->createIndex(
            'idx_product_is_archived_archived_at',
            $table,
            ['is_archived', 'archived_at']
        );
    }

    public function safeDown(): void
    {
        $table = '{{%product}}';

        $this->dropIndex(
            'idx_product_is_archived_archived_at',
            $table
        );

        $this->dropColumn(
            $table,
            'archived_at'
        );
    }
}