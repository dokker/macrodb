<?php

namespace App\Http\Resources;

use App\Models\FoodAlias;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FoodAlias
 */
class FoodAliasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_id' => $this->food_id,
            'name' => $this->name,
            'lang' => $this->lang,
        ];
    }
}
