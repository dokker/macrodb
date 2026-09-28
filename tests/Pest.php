<?php

use App\Enums\FoodSource;
use App\Models\Food;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

pest()->use(RefreshDatabase::class)
    ->in('Feature');

function foodPer100g(string $name, float $kcal, float $protein = 0, float $carbs = 0, float $fat = 0): Food
{
    return Food::create([
        'source' => FoodSource::Custom,
        'name' => $name,
        'kcal' => $kcal,
        'protein' => $protein,
        'carbs' => $carbs,
        'fat' => $fat,
    ]);
}
