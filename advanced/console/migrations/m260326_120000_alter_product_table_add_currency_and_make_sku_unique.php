<?php

use yii\db\Migration;

class m260326_120000_alter_product_table_add_currency_and_make_sku_unique extends Migration
{
    private string $table = '{{%product}}';

    public function safeUp(): void
    {
        $this->addColumn(
            $this->table,
            'currency',
            $this->string(3)->notNull()->defaultValue('UAH')->after('price')
        );

        // Пустые SKU приводим к NULL, чтобы unique-индекс не падал на множественных ''
        $this->update($this->table, ['sku' => null], ['sku' => '']);

        $rawTable = $this->db->schema->getRawTableName($this->table);

        $duplicates = $this->db->createCommand("
            SELECT `sku`, COUNT(*) AS cnt
            FROM `{$rawTable}`
            WHERE `sku` IS NOT NULL AND `sku` <> ''
            GROUP BY `sku`
            HAVING COUNT(*) > 1
            LIMIT 20
        ")->queryAll();

        if (!empty($duplicates)) {
            $items = [];
            foreach ($duplicates as $row) {
                $items[] = "{$row['sku']} (x{$row['cnt']})";
            }

            throw new RuntimeException(
                'Нельзя создать UNIQUE INDEX на product.sku: найдены дубли SKU: ' . implode(', ', $items)
            );
        }

        $this->dropIndex('idx_product_sku', $this->table);
        $this->createIndex('uq_product_sku', $this->table, 'sku', true);
    }

    public function safeDown(): void
    {
        $this->dropIndex('uq_product_sku', $this->table);
        $this->createIndex('idx_product_sku', $this->table, 'sku', false);

        $this->dropColumn($this->table, 'currency');
    }
}