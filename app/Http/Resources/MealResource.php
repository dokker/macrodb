<?php

namespace App\Http\Resources;

use App\Models\Meal;
use App\Models\MealItem;
use App\Support\Nutrients;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meal
 */
class MealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eaten_at' => $this->eaten_at->toIso8601String(),
            'meal_type' => $this->meal_type->value,
            'note' => $this->note,
            'total' => $this->items->reduce(
                fn (Nutrients $sum, MealItem $item): Nutrients => $sum->plus($item->nutrients()),
                Nutrients::zero(),
            )->toArray(),
            'items' => $this->items->map(fn (MealItem $item): array => [
                'id' => $item->id,
                'name' => $item->food?->name ?? $item->recipe->name,
                'grams' => $item->grams,
                'source_text' => $item->source_text,
                'input_method' => $item->input_method->value,
                'nutrients' => $item->nutrients()->toArray(),
            ])->all(),
        ];
    }
}
