<?php

namespace App\Models;

use App\Enums\MealType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['eaten_at', 'meal_type', 'note'])]
class Meal extends Model
{
    /**
     * @return HasMany<MealItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }

    protected function casts(): array
    {
        return [
            'eaten_at' => 'immutable_datetime',
            'meal_type' => MealType::class,
        ];
    }
}
