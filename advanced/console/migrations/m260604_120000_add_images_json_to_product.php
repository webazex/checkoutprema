<?php


use yii\db\Migration;

final class m260604_120000_add_images_json_to_product extends Migration
{
    public function safeUp(): void
    {
        if ($this->hasColumn('{{%product}}', 'images_json')) {
            return;
        }

        $this->addColumn(
            '{{%product}}',
            'images_json',
            $this->text()->null()->after('thumbnail_url')
        );
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        $schema = $this->db->schema->getTableSchema($tableName);

        return $schema !== null && isset($schema->columns[$columnName]);
    }

    public function safeDown(): void
    {
        if (!$this->hasColumn('{{%product}}', 'images_json')) {
            return;
        }

        $this->dropColumn('{{%product}}', 'images_json');
    }
}