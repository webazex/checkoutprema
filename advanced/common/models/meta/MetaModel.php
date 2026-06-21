<?php

declare(strict_types=1);

namespace common\models\meta;

use common\models\BaseModel;
use RuntimeException;

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

    public static function find(): MetaQuery
    {
        return new MetaQuery(static::class);
    }

    /**
     * Synchronizes all text meta values belonging to one key prefix.
     *
     * The supplied values are treated as the complete authoritative state
     * for this entity and prefix:
     *
     * - missing local rows are created;
     * - changed rows are updated;
     * - local rows absent from $values are deleted;
     * - meta rows with another prefix are not touched.
     *
     * @param array<string, string|null> $values
     * @return array{upserted:int, deleted:int, unchanged:int}
     */
    public static function syncTextValuesByPrefix(
        string $entityType,
        int    $entityId,
        string $prefix,
        array  $values,
    ): array
    {
        if ($entityId < 1) {
            throw new RuntimeException('Meta entity ID must be greater than zero.');
        }

        $prefix = trim($prefix);

        if ($prefix === '') {
            throw new RuntimeException('Meta key prefix must not be empty.');
        }

        $normalizedValues = [];

        foreach ($values as $key => $value) {
            $key = trim((string)$key);

            if ($key === '' || !str_starts_with($key, $prefix)) {
                continue;
            }

            $normalizedValues[substr($key, 0, 128)] = $value !== null
                ? trim((string)$value)
                : null;
        }

        return static::getDb()->transaction(
            static function () use ($entityType, $entityId, $prefix, $normalizedValues): array {
                /** @var self[] $existingRows */
                $existingRows = static::find()
                    ->forEntity($entityType, $entityId)
                    ->andWhere(['like', 'key', $prefix . '%', false])
                    ->all();

                $existingByKey = [];

                foreach ($existingRows as $row) {
                    $existingByKey[(string)$row->key] = $row;
                }

                $stats = [
                    'upserted' => 0,
                    'deleted' => 0,
                    'unchanged' => 0,
                ];

                foreach ($normalizedValues as $key => $value) {
                    $meta = $existingByKey[$key] ?? null;

                    if (!$meta instanceof self) {
                        $meta = new self();
                        $meta->entity_type = $entityType;
                        $meta->entity_id = $entityId;
                        $meta->key = $key;
                    } else {
                        unset($existingByKey[$key]);
                    }

                    $currentValue = $meta->value !== null
                        ? trim((string)$meta->value)
                        : null;

                    if (
                        !$meta->isNewRecord
                        && $currentValue === $value
                        && $meta->value_int === null
                        && $meta->value_decimal === null
                    ) {
                        $stats['unchanged']++;
                        continue;
                    }

                    $meta->value = $value;
                    $meta->value_int = null;
                    $meta->value_decimal = null;

                    if (!$meta->save(false)) {
                        throw new RuntimeException(sprintf(
                            'Failed to save meta "%s" for %s #%d.',
                            $key,
                            $entityType,
                            $entityId,
                        ));
                    }

                    $stats['upserted']++;
                }

                if ($existingByKey !== []) {
                    $ids = array_map(
                        static fn(self $row): int => (int)$row->id,
                        array_values($existingByKey),
                    );

                    $stats['deleted'] = static::deleteAll(['id' => $ids]);
                }

                return $stats;
            }
        );
    }

    public static function deleteForEntity(string $entityType, int $entityId): int
    {
        if ($entityId < 1) {
            return 0;
        }

        return static::deleteAll([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
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
}
