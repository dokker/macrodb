<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Live Open Food Facts lookup, used only when a barcode is missing from the local database.
 */
class OpenFoodFactsClient
{
    /**
     * Per-100 g attributes of a `foods` row, or null when OFF has no usable product for the barcode.
     *
     * @return array{external_id: string, barcode: string, name: string, brand: ?string, kcal: float, protein: float, carbs: float, fat: float, fiber: ?float, sugar: ?float, serving_size_g: ?float}|null
     */
    public function findByBarcode(string $barcode): ?array
    {
        $response = Http::baseUrl(config('services.open_food_facts.base_url'))
            ->withUserAgent(config('services.open_food_facts.user_agent'))
            ->timeout(8)
            ->get("/api/v2/product/{$barcode}.json", [
                'fields' => 'code,product_name,product_name_hu,brands,nutriments,serving_quantity',
            ]);

        if (! $response->successful() || $response->json('status') !== 1) {
            return null;
        }

        $product = $response->json('product');
        $nutriments = $product['nutriments'] ?? [];
        $name = $product['product_name_hu'] ?: ($product['product_name'] ?? null);

        // A product without a name or the four core macros cannot produce trustworthy totals.
        foreach (['energy-kcal_100g', 'proteins_100g', 'carbohydrates_100g', 'fat_100g'] as $key) {
            if (! is_numeric($nutriments[$key] ?? null)) {
                return null;
            }
        }

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $optional = fn (string $key): ?float => is_numeric($nutriments[$key] ?? null) ? (float) $nutriments[$key] : null;
        $brand = trim(explode(',', (string) ($product['brands'] ?? ''))[0]);
        $serving = $product['serving_quantity'] ?? null;

        return [
            'external_id' => (string) ($product['code'] ?? $barcode),
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
        ];
    }
}
