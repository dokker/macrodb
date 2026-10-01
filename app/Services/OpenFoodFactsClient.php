<?php

namespace App\Services;

use App\Support\OpenFoodFactsProduct;
use Illuminate\Support\Facades\Http;

/**
 * Live Open Food Facts lookup, used only when a barcode is missing from the local database.
 */
class OpenFoodFactsClient
{
    /**
     * Attributes of a `foods` row, or null when OFF has no usable product for the barcode.
     *
     * @return array<string, mixed>|null
     */
    public function findByBarcode(string $barcode): ?array
    {
        $response = Http::baseUrl(config('services.open_food_facts.base_url'))
            ->withUserAgent(config('services.open_food_facts.user_agent'))
            ->timeout(8)
            ->get("/api/v2/product/{$barcode}.json", [
                'fields' => 'code,product_name,product_name_hu,product_name_en,brands,nutriments,serving_quantity',
            ]);

        if (! $response->successful() || $response->json('status') !== 1) {
            return null;
        }

        return OpenFoodFactsProduct::toFoodAttributes($response->json('product'), $barcode);
    }
}
