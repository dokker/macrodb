<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'total_weight_g' => ['required', 'numeric', 'gt:0'],
            'default_portion_g' => ['nullable', 'numeric', 'gt:0'],
            'is_dish' => ['boolean'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.food_id' => ['required', 'integer', 'exists:foods,id'],
            'ingredients.*.grams' => ['required', 'numeric', 'gt:0'],
            'portions' => ['array'],
            'portions.*.label' => ['required', 'string', 'max:255'],
            'portions.*.grams' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
