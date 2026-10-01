<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recipe
 */
class RecipeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'recipe',
            'id' => $this->id,
            'name' => $this->name,
            'total_weight_g' => $this->total_weight_g,
            'default_portion_g' => $this->default_portion_g,
            'is_dish' => $this->is_dish,
            'ingredient_total_g' => $this->whenLoaded('items', fn (): float => $this->items->sum('grams')),
            'ingredients' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                'food_id' => $item->food_id,
                'name' => $item->food->displayName(),
                'grams' => $item->grams,
                'per_100g' => $item->food->nutrientsPer100g()->toArray(),
            ])->all()),
            'per_100g' => $this->nutrientsPer100g()->toArray(),
            'portions' => $this->portions->map(fn ($portion): array => ['label' => $portion->label, 'grams' => $portion->grams])->all(),
        ];
    }
}
