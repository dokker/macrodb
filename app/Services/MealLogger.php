<?php

namespace App\Services;

use App\Enums\InputMethod;
use App\Enums\MealType;
use App\Models\Meal;
use App\Models\MealItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Writes the meal log. The single path behind `log_meal`, `POST /meals` and the approved photo draft.
 */
class MealLogger
{
    /**
     * @param  list<array{food_id?: int|null, recipe_id?: int|null, grams: float|int, source_text?: string|null, input_method?: InputMethod}>  $items
     */
    public function log(CarbonInterface $eatenAt, MealType $mealType, array $items, ?string $note = null): Meal
    {
        if ($items === []) {
            throw new InvalidArgumentException('A meal needs at least one item.');
        }

        foreach ($items as $item) {
            $this->assertValidItem($item);
        }

        return DB::transaction(function () use ($eatenAt, $mealType, $items, $note): Meal {
            $meal = Meal::create([
                'eaten_at' => $eatenAt->utc(),
                'meal_type' => $mealType,
                'note' => $note,
            ]);

            foreach ($items as $item) {
                $meal->items()->create([
                    'food_id' => $item['food_id'] ?? null,
                    'recipe_id' => $item['recipe_id'] ?? null,
                    'grams' => $item['grams'],
                    'source_text' => $item['source_text'] ?? null,
                    'input_method' => $item['input_method'] ?? InputMethod::Text,
                ]);
            }

            return $meal->load('items.food', 'items.recipe.items.food');
        });
    }

    public function updateItemGrams(MealItem $item, float $grams): Meal
    {
        $this->assertPositiveGrams($grams);

        $item->update(['grams' => $grams]);

        return $this->reload($item->meal);
    }

    /**
     * Removes the item; a meal left without items is removed too. Returns the meal, or null when it is gone.
     */
    public function deleteItem(MealItem $item): ?Meal
    {
        $meal = $item->meal;

        DB::transaction(function () use ($item, $meal): void {
            $item->delete();

            if (! $meal->items()->exists()) {
                $meal->delete();
            }
        });

        return $meal->exists ? $this->reload($meal) : null;
    }

    private function reload(Meal $meal): Meal
    {
        return $meal->load('items.food', 'items.recipe.items.food');
    }

    /**
     * @param  array{food_id?: int|null, recipe_id?: int|null, grams: float|int}  $item
     */
    private function assertValidItem(array $item): void
    {
        if (isset($item['food_id']) === isset($item['recipe_id'])) {
            throw new InvalidArgumentException('Each item needs exactly one of food_id and recipe_id.');
        }

        $this->assertPositiveGrams($item['grams']);
    }

    private function assertPositiveGrams(float|int $grams): void
    {
        if ($grams <= 0) {
            throw new InvalidArgumentException('Grams must be greater than zero.');
        }
    }
}
