<?php

namespace common\models\product;
use common\models\product\ProductExternalMapQuery;
use common\models\BaseModel;

/**
 * @property int $id
 * @property int $product_id
 * @property string $external_source
 * @property string $external_id
 * @property string|null $sku_snapshot
 * @property int $created_at
 * @property int $updated_at
 *
 * @property ProductModel $product
 */
class ProductExternalMapModel extends BaseModel
{
    public const SOURCE_WIX = 'wix';
    public const SOURCE_PROM = 'prom';

    public const SOURCES = [
        self::SOURCE_WIX,
        self::SOURCE_PROM,
    ];

    public static function tableName(): string
    {
        return '{{%product_external_map}}';
    }

    public static function find(): ProductExternalMapQuery
    {
        return new ProductExternalMapQuery(static::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['product_id', 'external_source', 'external_id'], 'required'],
            [['product_id', 'created_at', 'updated_at'], 'integer'],
            [['external_source'], 'string', 'max' => 32],
            [['external_id'], 'string', 'max' => 100],
            [['sku_snapshot'], 'string', 'max' => 64],

            [['external_source'], 'in', 'range' => self::SOURCES],

            [['external_source', 'external_id'], 'unique', 'targetAttribute' => ['external_source', 'external_id']],
            [['external_source', 'product_id'], 'unique', 'targetAttribute' => ['external_source', 'product_id']],

            [
                ['product_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => ProductModel::class,
                'targetAttribute' => ['product_id' => 'id'],
            ],
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'product_id' => 'Товар',
            'external_source' => 'Внешний источник',
            'external_id' => 'Внешний ID',
            'sku_snapshot' => 'SKU snapshot',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getProduct()
    {
        return $this->hasOne(ProductModel::class, ['id' => 'product_id']);
    }

    public function getIsWix(): bool
    {
        return $this->external_source === self::SOURCE_WIX;
    }

    public function getIsProm(): bool
    {
        return $this->external_source === self::SOURCE_PROM;
    }
}