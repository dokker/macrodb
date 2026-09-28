<?php

use App\Enums\InputMethod;
use App\Enums\MealType;
use App\Models\Meal;
use App\Models\Recipe;
use App\Services\MealLogger;
use Carbon\CarbonImmutable;

it('logs a meal with food and recipe items and derives macros from grams', function () {
    $oats = foodPer100g('Zabpehely', kcal: 370, protein: 13, carbs: 60, fat: 7);
    $recipe = Recipe::create(['name' => 'Zabkása', 'total_weight_g' => 200]);
    $recipe->items()->create(['food_id' => $oats->id, 'grams' => 100]);

    $meal = app(MealLogger::class)->log(
        CarbonImmutable::parse('2026-09-28 08:00', 'Europe/Budapest'),
        MealType::Breakfast,
        [
            ['food_id' => $oats->id, 'grams' => 60, 'source_text' => '60 g zabpehely'],
            ['recipe_id' => $recipe->id, 'grams' => 100, 'input_method' => InputMethod::Barcode],
        ],
    );

    expect($meal->eaten_at->toDateTimeString())->toBe('2026-09-28 06:00:00')
        ->and($meal->items)->toHaveCount(2)
        ->and($meal->items[0]->input_method)->toBe(InputMethod::Text)
        ->and($meal->items[0]->nutrients()->kcal)->toBe(222.0)
        ->and($meal->items[1]->nutrients()->kcal)->toBe(185.0);
});

it('rejects invalid items without saving anything', function (array $item) {
    $food = foodPer100g('Rizs', kcal: 130);

    expect(fn () => app(MealLogger::class)->log(now(), MealType::Lunch, [
        ['food_id' => $food->id, 'grams' => 50],
        ...[$item + ['food_id' => $food->id]],
    ]))->toThrow(InvalidArgumentException::class);

    expect(Meal::count())->toBe(0);
})->with([
    'both food and recipe' => [['recipe_id' => 1, 'grams' => 10]],
    'zero grams' => [['grams' => 0]],
]);

it('rejects a meal without items', function () {
    expect(fn () => app(MealLogger::class)->log(now(), MealType::Snack, []))
        ->toThrow(InvalidArgumentException::class);
});

it('updates the grams of an item', function () {
    $food = foodPer100g('Rizs', kcal: 130);
    $meal = app(MealLogger::class)->log(now(), MealType::Lunch, [['food_id' => $food->id, 'grams' => 100]]);

    $updated = app(MealLogger::class)->updateItemGrams($meal->items[0], 250);

    expect($updated->items[0]->nutrients()->kcal)->toBe(325.0);
});

it('removes the meal together with its last item', function () {
    $food = foodPer100g('Rizs', kcal: 130);
    $meal = app(MealLogger::class)->log(now(), MealType::Lunch, [
        ['food_id' => $food->id, 'grams' => 100],
        ['food_id' => $food->id, 'grams' => 50],
    ]);

    expect(app(MealLogger::class)->deleteItem($meal->items[0])->items)->toHaveCount(1)
        ->and(app(MealLogger::class)->deleteItem($meal->fresh()->items[0]))->toBeNull()
        ->and(Meal::count())->toBe(0);
});
