<?php

namespace App\Models;

use App\Support\Nutrients;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'total_weight_g', 'default_portion_g', 'is_dish'])]
class Recipe extends Model
{
    /**
     * @return HasMany<RecipeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /**
     * @return HasMany<FoodPortion, $this>
     */
    public function portions(): HasMany
    {
        return $this->hasMany(FoodPortion::class);
    }

    /**
     * Ingredient totals spread over the cooked weight, since cooking changes the dish's mass.
     */
    public function nutrientsPer100g(): Nutrients
    {
        $total = $this->items->reduce(
            fn (Nutrients $sum, RecipeItem $item): Nutrients => $sum->plus($item->nutrients()),
            Nutrients::zero(),
        );

        return $total->scale(100 / $this->total_weight_g);
    }

    protected function casts(): array
    {
        return [
            'total_weight_g' => 'float',
            'default_portion_g' => 'float',
            'is_dish' => 'boolean',
        ];
    }
}
