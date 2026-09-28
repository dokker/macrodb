<?php

namespace App\Services;

use App\Models\DailyTarget;
use App\Models\Meal;
use App\Models\MealItem;
use App\Support\Nutrients;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Daily and range summaries, computed on demand from the meal log so corrections show up immediately.
 * Days are cut in Europe/Budapest while timestamps stay in UTC.
 */
class DailySummary
{
    public const string TIMEZONE = 'Europe/Budapest';

    /**
     * @return array{date: string, total: array<string, float|null>, meals: list<array<string, mixed>>, target: array<string, int|float>|null, deviation: array<string, float>|null}
     */
    public function forDate(CarbonImmutable|string $date): array
    {
        $day = $this->day($date);

        return $this->build($day, $this->mealsBetween($day, $day));
    }

    /**
     * @return list<array{date: string, total: array<string, float|null>, meals: list<array<string, mixed>>, target: array<string, int|float>|null, deviation: array<string, float>|null}>
     */
    public function forRange(CarbonImmutable|string $from, CarbonImmutable|string $to): array
    {
        $first = $this->day($from);
        $last = $this->day($to);

        $mealsByDate = $this->mealsBetween($first, $last)
            ->groupBy(fn (Meal $meal): string => $meal->eaten_at->setTimezone(self::TIMEZONE)->toDateString());

        $summaries = [];

        for ($day = $first; $day <= $last; $day = $day->addDay()) {
            $summaries[] = $this->build($day, $mealsByDate->get($day->toDateString(), collect()));
        }

        return $summaries;
    }

    private function day(CarbonImmutable|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();
    }

    /**
     * @return Collection<int, Meal>
     */
    private function mealsBetween(CarbonImmutable $first, CarbonImmutable $last): Collection
    {
        return Meal::query()
            ->where('eaten_at', '>=', $first->utc())
            ->where('eaten_at', '<', $last->addDay()->utc())
            ->with('items.food', 'items.recipe.items.food')
            ->orderBy('eaten_at')
            ->get();
    }

    /**
     * @param  Collection<int, Meal>  $meals
     * @return array{date: string, total: array<string, float|null>, meals: list<array<string, mixed>>, target: array<string, int|float>|null, deviation: array<string, float>|null}
     */
    private function build(CarbonImmutable $day, Collection $meals): array
    {
        $mealSummaries = $meals->map(fn (Meal $meal): array => [
            'id' => $meal->id,
            'eaten_at' => $meal->eaten_at->toIso8601String(),
            'meal_type' => $meal->meal_type->value,
            'note' => $meal->note,
            'total' => $this->total($meal)->toArray(),
            'items' => $meal->items->map(fn (MealItem $item): array => [
                'id' => $item->id,
                'name' => $item->food?->name ?? $item->recipe->name,
                'grams' => $item->grams,
                'source_text' => $item->source_text,
                'nutrients' => $item->nutrients()->toArray(),
            ])->all(),
        ])->values()->all();

        $total = $meals->reduce(
            fn (Nutrients $sum, Meal $meal): Nutrients => $sum->plus($this->total($meal)),
            Nutrients::zero(),
        );

        $target = DailyTarget::query()
            ->whereDate('valid_from', '<=', $day->toDateString())
            ->orderByDesc('valid_from')
            ->first();

        return [
            'date' => $day->toDateString(),
            'total' => $total->toArray(),
            'meals' => $mealSummaries,
            'target' => $target === null ? null : [
                'kcal' => $target->kcal,
                'protein' => $target->protein,
                'carbs' => $target->carbs,
                'fat' => $target->fat,
            ],
            'deviation' => $target === null ? null : [
                'kcal' => round($total->kcal - $target->kcal, 1),
                'protein' => round($total->protein - $target->protein, 1),
                'carbs' => round($total->carbs - $target->carbs, 1),
                'fat' => round($total->fat - $target->fat, 1),
            ],
        ];
    }

    private function total(Meal $meal): Nutrients
    {
        return $meal->items->reduce(
            fn (Nutrients $sum, MealItem $item): Nutrients => $sum->plus($item->nutrients()),
            Nutrients::zero(),
        );
    }
}
