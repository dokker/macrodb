<?php

namespace App\Services;

use App\Enums\FoodSource;
use App\Models\Food;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and looks up foods and recipes: `create_food`, `create_recipe` and `get_food_by_barcode`.
 */
class FoodCatalog
{
    public function __construct(private OpenFoodFactsClient $openFoodFacts) {}

    /**
     * @param  array{name: string, kcal: float|int, protein: float|int, carbs: float|int, fat: float|int, fiber?: float|int|null, sugar?: float|int|null, brand?: ?string, barcode?: ?string, serving_size_g?: float|int|null}  $attributes
     */
    public function createFood(array $attributes): Food
    {
        return Food::create([...$attributes, 'source' => FoodSource::Custom]);
    }

    /**
     * Local database first; Open Food Facts only on a miss, and the hit is persisted.
     */
    public function findByBarcode(string $barcode): ?Food
    {
        $food = Food::where('barcode', $barcode)->first();

        if ($food !== null) {
            return $food;
        }

        $product = $this->openFoodFacts->findByBarcode($barcode);

        if ($product === null) {
            return null;
        }

        return Food::create([...$product, 'source' => FoodSource::OpenFoodFacts]);
    }

    /**
     * Creates a recipe from validated request data (REST body or MCP tool arguments).
     *
     * @param  array{name: string, total_weight_g: float|int, ingredients: list<array{food_id: int, grams: float|int}>, default_portion_g?: float|int|null, is_dish?: bool, portions?: list<array{label: string, grams: float|int}>}  $data
     */
    public function createRecipeFromInput(array $data): Recipe
    {
        return $this->createRecipe(
            $data['name'],
            (float) $data['total_weight_g'],
            $data['ingredients'],
            isset($data['default_portion_g']) ? (float) $data['default_portion_g'] : null,
            $data['is_dish'] ?? true,
            $data['portions'] ?? [],
        );
    }

    /**
     * @param  list<array{food_id: int, grams: float|int}>  $ingredients
     * @param  list<array{label: string, grams: float|int}>  $portions
     */
    public function createRecipe(string $name, float $totalWeightG, array $ingredients, ?float $defaultPortionG = null, bool $isDish = true, array $portions = []): Recipe
    {
        if ($ingredients === []) {
            throw new InvalidArgumentException('A recipe needs at least one ingredient.');
        }

        if ($totalWeightG <= 0) {
            throw new InvalidArgumentException('The finished weight must be greater than zero.');
        }

        return DB::transaction(function () use ($name, $totalWeightG, $ingredients, $defaultPortionG, $isDish, $portions): Recipe {
            $recipe = Recipe::create([
                'name' => $name,
                'total_weight_g' => $totalWeightG,
                'default_portion_g' => $defaultPortionG,
                'is_dish' => $isDish,
            ]);

            $recipe->items()->createMany($ingredients);
            $recipe->portions()->createMany($portions);

            return $recipe->load('items.food', 'portions');
        });
    }
}
