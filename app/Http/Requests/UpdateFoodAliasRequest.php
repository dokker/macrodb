<?php

namespace App\Http\Requests;

use App\Models\FoodAlias;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFoodAliasRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        /** @var FoodAlias $alias */
        $alias = $this->route('alias');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('food_aliases', 'name')->where('food_id', $alias->food_id)->where('lang', $alias->lang)->ignore($alias),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'Ez a keresőnév már megvan ennél az ételnél.'];
    }
}
