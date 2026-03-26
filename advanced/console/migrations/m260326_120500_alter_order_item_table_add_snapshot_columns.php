<?php

use yii\db\Migration;

class m260326_120500_alter_order_item_table_add_snapshot_columns extends Migration
{
    private string $table = '{{%order_item}}';

    public function safeUp(): void
    {
        $this->addColumn($this->table, 'sku_snapshot', $this->string(64)->null()->append('AFTER `title`'));
        $this->addColumn($this->table, 'currency', $this->string(3)->notNull()->defaultValue('UAH')->append('AFTER `subtotal`'));
        $this->addColumn($this->table, 'product_payload_snapshot', 'LONGTEXT NULL');
        $this->addColumn($this->table, 'created_at', $this->integer()->unsigned()->null());
        $this->addColumn($this->table, 'updated_at', $this->integer()->unsigned()->null());

        $rawOrderItemTable = $this->db->schema->getRawTableName($this->table);
        $rawProductTable = $this->db->schema->getRawTableName('{{%product}}');
        $rawOrderTable = $this->db->schema->getRawTableName('{{%order}}');

        $this->execute("
            UPDATE `{$rawOrderItemTable}` oi
            LEFT JOIN `{$rawProductTable}` p ON p.id = oi.product_id
            LEFT JOIN `{$rawOrderTable}` o ON o.id = oi.order_id
            SET
                oi.sku_snapshot = p.sku,
                oi.created_at = o.created_at,
                oi.updated_at = o.updated_at
            WHERE oi.created_at IS NULL OR oi.updated_at IS NULL
        ");

        $this->alterColumn($this->table, 'created_at', $this->integer()->unsigned()->notNull());
        $this->alterColumn($this->table, 'updated_at', $this->integer()->unsigned()->notNull());

        $this->createIndex('idx_order_item_sku_snapshot', $this->table, 'sku_snapshot');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_order_item_sku_snapshot', $this->table);

        $this->dropColumn($this->table, 'updated_at');
        $this->dropColumn($this->table, 'created_at');
        $this->dropColumn($this->table, 'product_payload_snapshot');
        $this->dropColumn($this->table, 'currency');
        $this->dropColumn($this->table, 'sku_snapshot');
    }
}