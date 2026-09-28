<?php

namespace App\Support;

/**
 * An amount of nutrients. Fiber, sugar and every micronutrient are null when any contributing source lacks them.
 */
final readonly class Nutrients
{
    /**
     * The tracked micronutrients; each key is also the `foods` column that stores it per 100 g.
     *
     * @var list<string>
     */
    public const array MICROS = [
        'saturated_fat_g',
        'sodium_mg',
        'potassium_mg',
        'calcium_mg',
        'iron_mg',
        'magnesium_mg',
        'vitamin_c_mg',
        'vitamin_d_ug',
    ];

    /**
     * @param  array<string, float|null>  $micros  keyed by MICROS; a missing key means unknown
     */
    public function __construct(
        public float $kcal,
        public float $protein,
        public float $carbs,
        public float $fat,
        public ?float $fiber = null,
        public ?float $sugar = null,
        public array $micros = [],
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0, 0, 0, array_fill_keys(self::MICROS, 0.0));
    }

    public function scale(float $factor): self
    {
        return new self(
            $this->kcal * $factor,
            $this->protein * $factor,
            $this->carbs * $factor,
            $this->fat * $factor,
            $this->fiber === null ? null : $this->fiber * $factor,
            $this->sugar === null ? null : $this->sugar * $factor,
            array_map(fn (?float $value): ?float => $value === null ? null : $value * $factor, $this->micros),
        );
    }

    public function plus(self $other): self
    {
        return new self(
            $this->kcal + $other->kcal,
            $this->protein + $other->protein,
            $this->carbs + $other->carbs,
            $this->fat + $other->fat,
            $this->fiber === null || $other->fiber === null ? null : $this->fiber + $other->fiber,
            $this->sugar === null || $other->sugar === null ? null : $this->sugar + $other->sugar,
            $this->plusMicros($other),
        );
    }

    /**
     * @return array<string, float|null>
     */
    private function plusMicros(self $other): array
    {
        $sum = [];

        foreach (self::MICROS as $key) {
            $a = $this->micros[$key] ?? null;
            $b = $other->micros[$key] ?? null;
            $sum[$key] = $a === null || $b === null ? null : $a + $b;
        }

        return $sum;
    }

    /**
     * @return array{kcal: float, protein: float, carbs: float, fat: float, fiber: ?float, sugar: ?float, micros: array<string, float|null>}
     */
    public function toArray(): array
    {
        $round = fn (?float $value): ?float => $value === null ? null : round($value, 1);

        return [
            'kcal' => $round($this->kcal),
            'protein' => $round($this->protein),
            'carbs' => $round($this->carbs),
            'fat' => $round($this->fat),
            'fiber' => $round($this->fiber),
            'sugar' => $round($this->sugar),
            'micros' => array_combine(
                self::MICROS,
                array_map(fn (string $key): ?float => ($this->micros[$key] ?? null) === null ? null : round($this->micros[$key], 2), self::MICROS),
            ),
        ];
    }
}
