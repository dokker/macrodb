<?php

namespace App\Services;

use App\Enums\FoodSource;
use App\Exceptions\RecipeInUseException;
use App\Models\Food;
use App\Models\FoodAlias;
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
     * Sets the user's own name for a food; an empty name, or one equal to the source name, restores the original.
     */
    public function renameFood(Food $food, ?string $displayName): Food
    {
        $displayName = trim((string) $displayName);

        $food->update(['display_name' => $displayName === '' || $displayName === $food->name ? null : $displayName]);

        return $food;
    }

    /**
     * Adds a search name (typically Hungarian for an English USDA food); an alias that already exists is kept as is.
     */
    public function addAlias(Food $food, string $name, string $lang = 'hu'): FoodAlias
    {
        return $food->aliases()->firstOrCreate(['name' => $name], ['lang' => $lang]);
    }

    public function renameAlias(FoodAlias $alias, string $name): FoodAlias
    {
        $alias->update(['name' => $name]);

        return $alias;
    }

    public function deleteAlias(FoodAlias $alias): void
    {
        $alias->delete();
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
     * @param  array{name: string, ingredients: list<array{food_id: int, grams: float|int}>, total_weight_g?: float|int|null, default_portion_g?: float|int|null, is_dish?: bool, portions?: list<array{label: string, grams: float|int}>}  $data
     */
    public function createRecipeFromInput(array $data): Recipe
    {
        return $this->createRecipe(
            $data['name'],
            $this->finishedWeight($data),
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
        $this->assertValidRecipe($ingredients, $totalWeightG);

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

    /**
     * Replaces a recipe's fields, ingredients and portions with the validated request data. Logged meals follow
     * automatically, because their macros are derived from the recipe.
     *
     * @param  array{name: string, ingredients: list<array{food_id: int, grams: float|int}>, total_weight_g?: float|int|null, default_portion_g?: float|int|null, is_dish?: bool, portions?: list<array{label: string, grams: float|int}>}  $data
     */
    public function updateRecipe(Recipe $recipe, array $data): Recipe
    {
        $totalWeightG = $this->finishedWeight($data);

        $this->assertValidRecipe($data['ingredients'], $totalWeightG);

        return DB::transaction(function () use ($recipe, $data, $totalWeightG): Recipe {
            $recipe->update([
                'name' => $data['name'],
                'total_weight_g' => $totalWeightG,
                'default_portion_g' => isset($data['default_portion_g']) ? (float) $data['default_portion_g'] : null,
                'is_dish' => $data['is_dish'] ?? true,
            ]);

            $recipe->items()->delete();
            $recipe->items()->createMany($data['ingredients']);
            $recipe->portions()->delete();
            $recipe->portions()->createMany($data['portions'] ?? []);

            return $recipe->load('items.food', 'portions');
        });
    }

    /**
     * @throws RecipeInUseException when meals were logged with the recipe
     */
    public function deleteRecipe(Recipe $recipe): void
    {
        if ($recipe->mealItems()->exists()) {
            throw new RecipeInUseException;
        }

        $recipe->delete();
    }

    /**
     * The given finished weight, or the plain sum of the ingredient grams when none is given.
     *
     * @param  array{ingredients: list<array{grams: float|int}>, total_weight_g?: float|int|null}  $data
     */
    private function finishedWeight(array $data): float
    {
        return isset($data['total_weight_g'])
            ? (float) $data['total_weight_g']
            : (float) array_sum(array_column($data['ingredients'], 'grams'));
    }

    /**
     * @param  list<array{food_id: int, grams: float|int}>  $ingredients
     */
    private function assertValidRecipe(array $ingredients, float $totalWeightG): void
    {
        if ($ingredients === []) {
            throw new InvalidArgumentException('A recipe needs at least one ingredient.');
        }

        if ($totalWeightG <= 0) {
            throw new InvalidArgumentException('The finished weight must be greater than zero.');
        }
    }
}
