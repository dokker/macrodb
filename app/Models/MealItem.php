<?php

namespace App\Models;

use App\Enums\InputMethod;
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

    protected function casts(): array
    {
        return [
            'grams' => 'float',
            'input_method' => InputMethod::class,
        ];
    }
}
