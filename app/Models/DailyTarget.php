<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily goals; the row with the latest valid_from on or before a day applies to it.
 */
#[Fillable(['valid_from', 'kcal', 'protein', 'carbs', 'fat'])]
class DailyTarget extends Model
{
    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_date',
            'kcal' => 'integer',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
        ];
    }
}
