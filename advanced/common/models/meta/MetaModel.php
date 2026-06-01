<?php

declare(strict_types=1);

namespace common\models\meta;

use common\models\BaseModel;

final class MetaModel extends BaseModel
{
    public const ENTITY_CUSTOMER = 'customer';
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_ORDER = 'order';
    public const ENTITY_ORDER_ITEM = 'order_item';
    public const ENTITY_PAYMENT = 'payment';

    public static function tableName(): string
    {
        return '{{%meta}}';
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['entity_type', 'entity_id', 'key'], 'required'],
            [['entity_id', 'value_int', 'created_at', 'updated_at'], 'integer'],
            [['value'], 'string'],
            [['value_decimal'], 'number'],
            [['entity_type'], 'string', 'max' => 32],
            [['key'], 'string', 'max' => 128],
            [['entity_type', 'entity_id', 'key'], 'unique', 'targetAttribute' => ['entity_type', 'entity_id', 'key']],
        ]);
    }

    public static function find(): MetaQuery
    {
        return new MetaQuery(static::class);
    }

    public static function upsertText(string $entityType, int $entityId, string $key, ?string $value): void
    {
        $meta = static::find()
            ->forEntity($entityType, $entityId)
            ->byKey($key)
            ->one();

        if (!$meta instanceof self) {
            $meta = new self();
            $meta->entity_type = $entityType;
            $meta->entity_id = $entityId;
            $meta->key = $key;
        }

        $meta->value = $value;
        $meta->value_int = null;
        $meta->value_decimal = null;
        $meta->save(false);
    }

    /**
     * @param string[] $keys
     * @return array<string, string|null>
     */
    public static function getEntityTextValues(string $entityType, int $entityId, array $keys): array
    {
        if ($entityId < 1 || $keys === []) {
            return [];
        }

        /** @var self[] $rows */
        $rows = static::find()
            ->forEntity($entityType, $entityId)
            ->andWhere(['key' => $keys])
            ->all();

        $result = [];

        foreach ($rows as $row) {
            $value = $row->value !== null ? trim((string)$row->value) : null;
            $result[(string)$row->key] = $value !== '' ? $value : null;
        }

        return $result;
    }
}