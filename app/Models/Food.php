<?php

namespace App\Models;

use App\Enums\FoodSource;
use App\Support\Nutrients;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "food" is uncountable for the pluralizer, so the table name must be explicit.
#[Table('foods')]
#[Fillable(['source', 'external_id', 'barcode', 'name', 'brand', 'kcal', 'protein', 'carbs', 'fat', 'fiber', 'sugar', 'serving_size_g'])]
class Food extends Model
{
    /**
     * @return HasMany<FoodAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(FoodAlias::class);
    }

    /**
     * @return HasMany<FoodPortion, $this>
     */
    public function portions(): HasMany
    {
        return $this->hasMany(FoodPortion::class);
    }

    public function nutrientsPer100g(): Nutrients
    {
        return new Nutrients($this->kcal, $this->protein, $this->carbs, $this->fat, $this->fiber, $this->sugar);
    }

    protected function casts(): array
    {
        return [
            'source' => FoodSource::class,
            'kcal' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
            'fiber' => 'float',
            'sugar' => 'float',
            'serving_size_g' => 'float',
        ];
    }
}
