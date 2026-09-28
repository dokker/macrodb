<?php

namespace App\Services;

use App\Models\DailyTarget;
use Illuminate\Support\Collection;

/**
 * Daily goals. The row with the latest valid_from on or before a day applies to it.
 */
class DailyTargets
{
    public function forDate(string $date): ?DailyTarget
    {
        return DailyTarget::query()
            ->whereDate('valid_from', '<=', $date)
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * @return Collection<int, DailyTarget>
     */
    public function all(): Collection
    {
        return DailyTarget::query()->orderByDesc('valid_from')->get();
    }

    /**
     * Sets the goals from a date on; an existing row for that exact date is replaced.
     */
    public function set(string $validFrom, int $kcal, float $protein, float $carbs, float $fat): DailyTarget
    {
        return DailyTarget::updateOrCreate(
            ['valid_from' => $validFrom],
            ['kcal' => $kcal, 'protein' => $protein, 'carbs' => $carbs, 'fat' => $fat],
        );
    }

    /**
     * @return array{id: int, valid_from: string, kcal: int, protein: float, carbs: float, fat: float}
     */
    public function toArray(DailyTarget $target): array
    {
        return [
            'id' => $target->id,
            'valid_from' => $target->valid_from->toDateString(),
            'kcal' => $target->kcal,
            'protein' => $target->protein,
            'carbs' => $target->carbs,
            'fat' => $target->fat,
        ];
    }
}
