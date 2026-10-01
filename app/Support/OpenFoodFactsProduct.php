<?php

namespace App\Support;

/**
 * Maps an Open Food Facts product (API or dump record) to the attributes of a `foods` row.
 */
final class OpenFoodFactsProduct
{
    /**
     * Null when the product lacks a name or one of the four core macros, since totals built on it would be untrustworthy.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>|null
     */
    public static function toFoodAttributes(array $product, ?string $fallbackBarcode = null): ?array
    {
        $barcode = (string) ($product['code'] ?? $fallbackBarcode ?? '');
        $nutriments = $product['nutriments'] ?? [];
        // `product_name` is in the product's main language (often Greek, Polish...), so English comes before it.
        $name = ($product['product_name_hu'] ?? null) ?: ($product['product_name_en'] ?? null) ?: ($product['product_name'] ?? null);

        foreach (['energy-kcal_100g', 'proteins_100g', 'carbohydrates_100g', 'fat_100g'] as $key) {
            if (! is_numeric($nutriments[$key] ?? null)) {
                return null;
            }
        }

        if ($barcode === '' || ! is_string($name) || trim($name) === '') {
            return null;
        }

        $optional = fn (string $key): ?float => is_numeric($nutriments[$key] ?? null) ? (float) $nutriments[$key] : null;
        $milli = fn (?float $grams): ?float => $grams === null ? null : $grams * 1000;
        $brand = trim(explode(',', (string) ($product['brands'] ?? ''))[0]);
        $serving = $product['serving_quantity'] ?? null;
        $salt = $optional('salt_100g');

        return [
            'external_id' => $barcode,
            'barcode' => $barcode,
            'name' => trim($name),
            'brand' => $brand === '' ? null : $brand,
            'kcal' => (float) $nutriments['energy-kcal_100g'],
            'protein' => (float) $nutriments['proteins_100g'],
            'carbs' => (float) $nutriments['carbohydrates_100g'],
            'fat' => (float) $nutriments['fat_100g'],
            'fiber' => $optional('fiber_100g'),
            'sugar' => $optional('sugars_100g'),
            'serving_size_g' => is_numeric($serving) && $serving > 0 ? (float) $serving : null,
            // OFF stores every micronutrient in grams per 100 g; convert to the units of Nutrients::MICROS.
            'saturated_fat_g' => $optional('saturated-fat_100g'),
            'sodium_mg' => $milli($optional('sodium_100g') ?? ($salt === null ? null : $salt * 0.4)),
            'potassium_mg' => $milli($optional('potassium_100g')),
            'calcium_mg' => $milli($optional('calcium_100g')),
            'iron_mg' => $milli($optional('iron_100g')),
            'magnesium_mg' => $milli($optional('magnesium_100g')),
            'vitamin_c_mg' => $milli($optional('vitamin-c_100g')),
            'vitamin_d_ug' => $milli($milli($optional('vitamin-d_100g'))),
        ];
    }
}
