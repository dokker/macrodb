<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyTargetRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'kcal' => ['required', 'integer', 'between:500,10000'],
            'protein' => ['required', 'numeric', 'between:0,1000'],
            'carbs' => ['required', 'numeric', 'between:0,1000'],
            'fat' => ['required', 'numeric', 'between:0,1000'],
        ];
    }
}
