<?php

namespace App\Models;

use App\Support\Nutrients;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recipe_id', 'food_id', 'grams'])]
class RecipeItem extends Model
{
    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function nutrients(): Nutrients
    {
        return $this->food->nutrientsPer100g()->scale($this->grams / 100);
    }

    protected function casts(): array
    {
        return [
            'grams' => 'float',
        ];
    }
}
