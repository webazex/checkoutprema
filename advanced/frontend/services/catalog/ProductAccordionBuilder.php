<?php

declare(strict_types=1);

namespace frontend\services\catalog;

use common\models\meta\MetaModel;
use common\models\product\ProductModel;
use Yii;
use yii\helpers\Html;

final class ProductAccordionBuilder
{
    private const META_FORM_KEYS = [
        'keycrm.custom.CT_1001',
        'keycrm.custom.ct_1001',
        'keycrm.custom.forma',
    ];

    private const META_MATERIAL_KEYS = [
        'keycrm.custom.CT_1002',
        'keycrm.custom.ct_1002',
        'keycrm.custom.material',
        'keycrm.custom.material-tovaru',
    ];

    private const META_KEYS = [
        'keycrm.custom.CT_1001',
        'keycrm.custom.ct_1001',
        'keycrm.custom.forma',
        'keycrm.custom.CT_1002',
        'keycrm.custom.ct_1002',
        'keycrm.custom.material',
        'keycrm.custom.material-tovaru',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(ProductModel $product): array
    {
        return [
            [
                'title' => Yii::t('frontend', 'Description'),
                'type' => 'html',
                'content' => $this->buildDescriptionHtml($product),
                'open' => true,
            ],
            [
                'title' => Yii::t('frontend', 'Characteristics'),
                'type' => 'table',
                'rows' => $this->buildCharacteristicRows($product),
                'open' => false,
            ],
        ];
    }

    private function buildDescriptionHtml(ProductModel $product): string
    {
        if (!empty($product->description)) {
            return (string)$product->description;
        }

        return '<p>' . Html::encode(Yii::t('frontend', 'Product description is being prepared.')) . '</p>';
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function buildCharacteristicRows(ProductModel $product): array
    {
        $meta = MetaModel::getEntityTextValues(
            MetaModel::ENTITY_PRODUCT,
            (int)$product->id,
            self::META_KEYS
        );

        $rows = [];

        $this->addRow($rows, Yii::t('frontend', 'Barcode'), $product->barcode);
        $this->addRow($rows, Yii::t('frontend', 'Form'), $this->pickMetaValue($meta, self::META_FORM_KEYS));
        $this->addRow($rows, Yii::t('frontend', 'Material'), $this->pickMetaValue($meta, self::META_MATERIAL_KEYS));
        $this->addRow($rows, Yii::t('frontend', 'Weight'), $this->formatWeight($product->weight_kg));
        $this->addRow(
            $rows,
            Yii::t('frontend', 'Size'),
            $this->formatSize($product->length_mm, $product->width_mm, $product->height_mm)
        );

        return $rows;
    }

    /**
     * @param array<int, array{label: string, value: string}> $rows
     */
    private function addRow(array &$rows, string $label, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $value = trim((string)$value);

        if ($value === '') {
            return;
        }

        $rows[] = [
            'label' => $label,
            'value' => $value,
        ];
    }

    /**
     * @param array<string, string|null> $meta
     * @param string[] $keys
     */
    private function pickMetaValue(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $meta[$key] ?? null;

            if ($value !== null && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return null;
    }

    private function formatWeight(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $kg = (float)$value;

        if ($kg <= 0) {
            return null;
        }

        if ($kg < 1) {
            return (string)((int)round($kg * 1000)) . ' г';
        }

        $formatted = number_format($kg, 3, '.', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . ' кг';
    }

    private function formatSize(mixed $length, mixed $width, mixed $height): ?string
    {
        $parts = [];

        foreach ([$length, $width, $height] as $value) {
            $value = (int)$value;

            if ($value <= 0) {
                continue;
            }

            $cm = $value / 10;
            $precision = $value % 10 === 0 ? 0 : 1;
            $parts[] = number_format($cm, $precision, '.', ' ');
        }

        if ($parts === []) {
            return null;
        }

        return implode(' × ', $parts) . ' см';
    }
}