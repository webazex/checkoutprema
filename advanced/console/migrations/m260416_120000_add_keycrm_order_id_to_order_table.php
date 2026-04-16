<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260416_120000_add_keycrm_order_id_to_order_table extends Migration
{
    private string $table = '{{%order}}';

    public function safeUp(): void
    {
        $this->addColumn(
            $this->table,
            'keycrm_order_id',
            $this->string(64)->null()->after('cart_id')
        );

        $this->createIndex(
            'uq_order_keycrm_order_id',
            $this->table,
            'keycrm_order_id',
            true
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('uq_order_keycrm_order_id', $this->table);
        $this->dropColumn($this->table, 'keycrm_order_id');
    }
}