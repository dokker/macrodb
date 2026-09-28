<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named portion ("1 szelet", "1 bögre") of either a food or a recipe, never both.
 */
#[Fillable(['food_id', 'recipe_id', 'label', 'grams'])]
class FoodPortion extends Model
{
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
        ];
    }
}
