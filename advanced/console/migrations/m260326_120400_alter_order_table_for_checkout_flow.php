<?php

use yii\db\Migration;
use yii\db\Expression;

class m260326_120400_alter_order_table_for_checkout_flow extends Migration
{
    private string $table = '{{%order}}';

    public function safeUp(): void
    {
        $this->addColumn($this->table, 'hash', $this->string(64)->null()->after('id'));
        $this->addColumn($this->table, 'cart_id', $this->integer()->null()->after('customer_id'));

        $this->addColumn($this->table, 'currency', $this->string(3)->notNull()->defaultValue('UAH')->after('total_amount'));
        $this->addColumn($this->table, 'subtotal_amount', $this->decimal(12, 2)->notNull()->defaultValue(0.00)->after('currency'));
        $this->addColumn($this->table, 'discount_amount', $this->decimal(12, 2)->notNull()->defaultValue(0.00)->after('subtotal_amount'));
        $this->addColumn($this->table, 'shipping_amount', $this->decimal(12, 2)->notNull()->defaultValue(0.00)->after('discount_amount'));

        $this->addColumn($this->table, 'customer_email', $this->string(255)->null()->after('payment_method'));
        $this->addColumn($this->table, 'customer_phone', $this->string(30)->null()->after('customer_email'));
        $this->addColumn($this->table, 'customer_first_name', $this->string(100)->null()->after('customer_phone'));
        $this->addColumn($this->table, 'customer_last_name', $this->string(100)->null()->after('customer_first_name'));

        $this->addColumn($this->table, 'source_type', $this->string(32)->notNull()->defaultValue('direct')->after('customer_last_name'));

        $this->addColumn($this->table, 'placed_at', $this->integer()->unsigned()->null()->after('source_type'));
        $this->addColumn($this->table, 'paid_at', $this->integer()->unsigned()->null()->after('placed_at'));
        $this->addColumn($this->table, 'cancelled_at', $this->integer()->unsigned()->null()->after('paid_at'));

        $rawOrderTable = $this->db->schema->getRawTableName($this->table);
        $rawCustomerTable = $this->db->schema->getRawTableName('{{%customer}}');

        $this->execute("
            UPDATE `{$rawOrderTable}` o
            INNER JOIN `{$rawCustomerTable}` c ON c.id = o.customer_id
            SET
                o.hash = CONCAT('ord_', o.id, '_', o.created_at),
                o.subtotal_amount = o.total_amount,
                o.customer_email = c.email,
                o.customer_phone = c.phone,
                o.customer_first_name = c.first_name,
                o.customer_last_name = c.last_name,
                o.placed_at = o.created_at
            WHERE o.hash IS NULL
        ");

        $this->alterColumn($this->table, 'hash', $this->string(64)->notNull());
        $this->alterColumn($this->table, 'customer_email', $this->string(255)->notNull());

        $this->createIndex('uq_order_hash', $this->table, 'hash', true);
        $this->createIndex('uq_order_cart_id', $this->table, 'cart_id', true);
        $this->createIndex('idx_order_status', $this->table, 'status');
        $this->createIndex('idx_order_payment_status', $this->table, 'payment_status');
        $this->createIndex('idx_order_source_type', $this->table, 'source_type');

        $this->addForeignKey(
            'fk_order_cart_id',
            $this->table,
            'cart_id',
            '{{%cart}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_order_cart_id', $this->table);

        $this->dropIndex('idx_order_source_type', $this->table);
        $this->dropIndex('idx_order_payment_status', $this->table);
        $this->dropIndex('idx_order_status', $this->table);
        $this->dropIndex('uq_order_cart_id', $this->table);
        $this->dropIndex('uq_order_hash', $this->table);

        $this->dropColumn($this->table, 'cancelled_at');
        $this->dropColumn($this->table, 'paid_at');
        $this->dropColumn($this->table, 'placed_at');
        $this->dropColumn($this->table, 'source_type');

        $this->dropColumn($this->table, 'customer_last_name');
        $this->dropColumn($this->table, 'customer_first_name');
        $this->dropColumn($this->table, 'customer_phone');
        $this->dropColumn($this->table, 'customer_email');

        $this->dropColumn($this->table, 'shipping_amount');
        $this->dropColumn($this->table, 'discount_amount');
        $this->dropColumn($this->table, 'subtotal_amount');
        $this->dropColumn($this->table, 'currency');

        $this->dropColumn($this->table, 'cart_id');
        $this->dropColumn($this->table, 'hash');
    }
}