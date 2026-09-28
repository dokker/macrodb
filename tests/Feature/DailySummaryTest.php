<?php

use App\Enums\MealType;
use App\Models\DailyTarget;
use App\Services\DailySummary;
use App\Services\MealLogger;
use Carbon\CarbonImmutable;

function logAt(string $budapestTime, float $grams, int $foodId, MealType $type = MealType::Lunch): void
{
    app(MealLogger::class)->log(
        CarbonImmutable::parse($budapestTime, 'Europe/Budapest'),
        $type,
        [['food_id' => $foodId, 'grams' => $grams]],
    );
}

it('totals a day per meal and cuts days in Budapest time', function () {
    $rice = foodPer100g('Rizs', kcal: 100, protein: 2, carbs: 20, fat: 1);

    logAt('2026-09-28 00:30', 100, $rice->id, MealType::Snack); // 22:30 UTC the day before
    logAt('2026-09-28 12:00', 200, $rice->id);
    logAt('2026-09-28 23:59', 100, $rice->id, MealType::Dinner);
    logAt('2026-09-29 00:01', 100, $rice->id);

    $summary = app(DailySummary::class)->forDate('2026-09-28');

    expect($summary['total']['kcal'])->toBe(400.0)
        ->and(collect($summary['meals'])->pluck('meal_type')->all())->toBe(['snack', 'ebéd', 'vacsora'])
        ->and($summary['meals'][1]['total']['protein'])->toBe(4.0)
        ->and($summary['target'])->toBeNull()
        ->and($summary['deviation'])->toBeNull();
});

it('reports the deviation from the target valid on that day', function () {
    $rice = foodPer100g('Rizs', kcal: 100, protein: 2, carbs: 20, fat: 1);
    DailyTarget::create(['valid_from' => '2026-01-01', 'kcal' => 2000, 'protein' => 100, 'carbs' => 250, 'fat' => 70]);
    DailyTarget::create(['valid_from' => '2026-09-01', 'kcal' => 1800, 'protein' => 120, 'carbs' => 200, 'fat' => 60]);
    DailyTarget::create(['valid_from' => '2026-10-01', 'kcal' => 1500, 'protein' => 120, 'carbs' => 150, 'fat' => 50]);
    logAt('2026-09-28 12:00', 500, $rice->id);

    $summary = app(DailySummary::class)->forDate('2026-09-28');

    expect($summary['target']['kcal'])->toBe(1800)
        ->and($summary['deviation'])->toBe(['kcal' => -1300.0, 'protein' => -110.0, 'carbs' => -100.0, 'fat' => -55.0]);
});

it('lists every day of a range, including days without meals', function () {
    $rice = foodPer100g('Rizs', kcal: 100);
    logAt('2026-09-26 12:00', 100, $rice->id);
    logAt('2026-09-28 12:00', 300, $rice->id);

    $days = app(DailySummary::class)->forRange('2026-09-26', '2026-09-28');

    expect(collect($days)->pluck('date')->all())->toBe(['2026-09-26', '2026-09-27', '2026-09-28'])
        ->and(collect($days)->pluck('total.kcal')->all())->toBe([100.0, 0.0, 300.0]);
});

it('leaves fiber unknown when an item lacks it', function () {
    $rice = foodPer100g('Rizs', kcal: 100);
    logAt('2026-09-28 12:00', 100, $rice->id);

    expect(app(DailySummary::class)->forDate('2026-09-28')['total']['fiber'])->toBeNull();
});
