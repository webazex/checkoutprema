<?php

namespace frontend\helpers\catalog;

use common\models\catalog\CatalogCategoryModel;
use common\models\product\ProductModel;
use frontend\services\catalog\ProductAccordionBuilder;
use Throwable;
use Yii;
use yii\helpers\Html;

final class ProductPageViewHelper
{
    private const GALLERY_SIZE = 4;

    /**
     * Builds the product gallery for the product page.
     *
     * Rules:
     * - Use the first image as the main image.
     * - Keep only the first 4 gallery slots.
     * - If fewer than 4 images are available, repeat them cyclically.
     */
    public static function buildGalleryImages(ProductModel $product, int $size = self::GALLERY_SIZE): array
    {
        $urls = [];

        self::collectImageUrls($product->thumbnail_url, $urls);

        foreach (self::getPossibleGalleryAttributes() as $attribute) {
            if ($product->hasAttribute($attribute)) {
                self::collectImageUrls($product->getAttribute($attribute), $urls);
            }
        }

        foreach (self::getPossibleGalleryProperties() as $property) {
            if (!$product->canGetProperty($property)) {
                continue;
            }

            try {
                self::collectImageUrls($product->{$property}, $urls);
            } catch (Throwable) {
                // Optional future relation/property is not available or failed to load.
            }
        }

        $urls = self::uniqueImageUrls($urls);

        if ($urls === []) {
            return [];
        }

        $gallery = [];
        $sourceCount = count($urls);

        for ($index = 0; $index < $size; $index++) {
            $gallery[] = $urls[$index % $sourceCount];
        }

        return $gallery;
    }

    public static function getMainImage(array $galleryImages): ?string
    {
        return $galleryImages[0] ?? null;
    }

    public static function getThumbnailImages(array $galleryImages): array
    {
        return array_slice($galleryImages, 1, self::GALLERY_SIZE - 1);
    }

    public static function formatPrice(ProductModel $product): string
    {
        $currency = trim((string)($product->currency ?: 'UAH'));

        return number_format((float)$product->price, 0, '.', ' ') . ' ' . $currency;
    }

    public static function buildImageAlt(ProductModel $product, CatalogCategoryModel $category): string
    {
        return trim((string)$product->name . ', ' . (string)$category->name);
    }

    public static function getSku(ProductModel $product): string
    {
        return trim((string)$product->sku);
    }

    public static function getQuantity(ProductModel $product): int
    {
        return max(0, (int)$product->quantity);
    }

    public static function buildAccordionItems(ProductModel $product): array
    {
        $items = (new ProductAccordionBuilder())->build($product);

        $items[] = [
            'title' => Yii::t('frontend', 'Reviews'),
            'type' => 'html',
            'content' => '<p>' . Html::encode(Yii::t('frontend', 'Reviews will be added soon.')) . '</p>',
            'open' => false,
        ];

        return $items;
    }

    private static function collectImageUrls(mixed $value, array &$urls): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            self::collectImageUrlsFromString($value, $urls);
            return;
        }

        if (is_object($value)) {
            $value = self::objectToArray($value);
        }

        if (!is_array($value)) {
            return;
        }

        foreach (self::getPossibleUrlKeys() as $key) {
            if (!empty($value[$key]) && is_string($value[$key])) {
                self::collectImageUrls($value[$key], $urls);
                return;
            }
        }

        foreach ($value as $item) {
            self::collectImageUrls($item, $urls);
        }
    }

    private static function collectImageUrlsFromString(string $value, array &$urls): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            self::collectImageUrls($decoded, $urls);
            return;
        }

        $url = self::normalizeImageUrl($value);

        if ($url !== null) {
            $urls[] = $url;
        }
    }

    private static function normalizeImageUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $url) || str_starts_with($url, '/')) {
            return $url;
        }

        return null;
    }

    private static function objectToArray(object $value): array
    {
        if (method_exists($value, 'toArray')) {
            try {
                $array = $value->toArray();

                if (is_array($array)) {
                    return $array;
                }
            } catch (Throwable) {
                // Fallback below.
            }
        }

        if (method_exists($value, 'getAttributes')) {
            try {
                $attributes = $value->getAttributes();

                if (is_array($attributes)) {
                    return $attributes;
                }
            } catch (Throwable) {
                // Fallback below.
            }
        }

        return get_object_vars($value);
    }

    private static function uniqueImageUrls(array $urls): array
    {
        $result = [];

        foreach ($urls as $url) {
            $url = self::normalizeImageUrl((string)$url);

            if ($url === null || isset($result[$url])) {
                continue;
            }

            $result[$url] = $url;
        }

        return array_values($result);
    }

    private static function getPossibleGalleryAttributes(): array
    {
        return [
            'images_json',
            'image_urls_json',
            'gallery_json',
            'photos_json',
            'image_urls',
            'gallery_urls',
        ];
    }

    private static function getPossibleGalleryProperties(): array
    {
        return [
            'images',
            'photos',
            'gallery',
        ];
    }

    private static function getPossibleUrlKeys(): array
    {
        return [
            'url',
            'src',
            'thumbnail_url',
            'image_url',
            'original_url',
            'full_url',
        ];
    }
}
