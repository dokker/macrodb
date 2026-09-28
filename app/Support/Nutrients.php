<?php

namespace App\Support;

/**
 * An amount of nutrients. Fiber and sugar are null when any contributing source lacks them.
 */
final readonly class Nutrients
{
    public function __construct(
        public float $kcal,
        public float $protein,
        public float $carbs,
        public float $fat,
        public ?float $fiber = null,
        public ?float $sugar = null,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0, 0, 0);
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
        );
    }

    /**
     * @return array{kcal: float, protein: float, carbs: float, fat: float, fiber: ?float, sugar: ?float}
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
        ];
    }
}
