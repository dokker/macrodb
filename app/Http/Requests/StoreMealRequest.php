<?php

namespace App\Http\Requests;

use App\Enums\InputMethod;
use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'eaten_at' => ['required', 'date'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.food_id' => ['required_without:items.*.recipe_id', 'prohibits:items.*.recipe_id', 'nullable', 'integer', 'exists:foods,id'],
            'items.*.recipe_id' => ['required_without:items.*.food_id', 'prohibits:items.*.food_id', 'nullable', 'integer', 'exists:recipes,id'],
            'items.*.grams' => ['required', 'numeric', 'gt:0'],
            'items.*.source_text' => ['nullable', 'string', 'max:255'],
            'items.*.input_method' => ['nullable', Rule::enum(InputMethod::class)],
        ];
    }
}
