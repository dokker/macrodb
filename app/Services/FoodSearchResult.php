<?php

namespace App\Services;

use App\Support\Nutrients;

final readonly class FoodSearchResult
{
    /**
     * @param  'food'|'recipe'  $type
     * @param  list<string>  $aliases
     * @param  list<array{label: string, grams: float}>  $portions
     */
    public function __construct(
        public string $type,
        public int $id,
        public string $name,
        public ?string $brand,
        public ?string $source,
        public array $aliases,
        public Nutrients $per100g,
        public ?float $defaultPortionG,
        public array $portions,
        public int $matchRank,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'source' => $this->source,
            'aliases' => $this->aliases,
            'per_100g' => $this->per100g->toArray(),
            'default_portion_g' => $this->defaultPortionG,
            'portions' => $this->portions,
        ];
    }
}
