<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFoodRequest extends FormRequest
{
    /**
     * A null or empty `display_name` restores the food's original name.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
