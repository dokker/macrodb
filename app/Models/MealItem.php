<?php

namespace App\Models;

use App\Enums\InputMethod;
use App\Support\Nutrients;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Either a food or a recipe, never both, logged in grams.
 */
#[Fillable(['meal_id', 'food_id', 'recipe_id', 'grams', 'source_text', 'input_method'])]
class MealItem extends Model
{
    /**
     * @return BelongsTo<Meal, $this>
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Nutrients of the logged grams, derived from the food's or recipe's per-100 g values.
     */
    public function nutrients(): Nutrients
    {
        $per100g = $this->food_id !== null
            ? $this->food->nutrientsPer100g()
            : $this->recipe->nutrientsPer100g();

        return $per100g->scale($this->grams / 100);
    }

    protected function casts(): array
    {
        return [
            'grams' => 'float',
            'input_method' => InputMethod::class,
        ];
    }
}
