<?php

use yii\db\Migration;

class m260326_120700_alter_payment_log_table_add_payment_link extends Migration
{
    private string $table = '{{%payment_log}}';

    public function safeUp(): void
    {
        $this->addColumn($this->table, 'payment_id', $this->integer()->null()->after('order_id'));
        $this->addColumn($this->table, 'provider', $this->string(32)->null()->after('payment_id'));
        $this->addColumn($this->table, 'event_type', $this->string(32)->null()->after('provider'));
        $this->addColumn($this->table, 'direction', $this->string(16)->null()->after('event_type'));

        $this->createIndex('idx_payment_log_payment_id', $this->table, 'payment_id');
        $this->createIndex('idx_payment_log_provider', $this->table, 'provider');
        $this->createIndex('idx_payment_log_event_type', $this->table, 'event_type');

        $this->addForeignKey(
            'fk_payment_log_payment_id',
            $this->table,
            'payment_id',
            '{{%payment}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_payment_log_payment_id', $this->table);

        $this->dropIndex('idx_payment_log_event_type', $this->table);
        $this->dropIndex('idx_payment_log_provider', $this->table);
        $this->dropIndex('idx_payment_log_payment_id', $this->table);

        $this->dropColumn($this->table, 'direction');
        $this->dropColumn($this->table, 'event_type');
        $this->dropColumn($this->table, 'provider');
        $this->dropColumn($this->table, 'payment_id');
    }
}