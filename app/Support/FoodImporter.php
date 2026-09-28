<?php

namespace App\Support;

use App\Enums\FoodSource;
use App\Models\Food;

/**
 * Bulk-upserts foods of one source, keyed by (source, external_id), so an import can be re-run safely.
 */
final class FoodImporter
{
    private const int CHUNK = 500;

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    private int $imported = 0;

    public function __construct(private readonly FoodSource $source) {}

    /**
     * @param  array<string, mixed>  $attributes  must include external_id
     */
    public function add(array $attributes): void
    {
        $this->buffer[] = $this->normalise($attributes);

        if (count($this->buffer) >= self::CHUNK) {
            $this->flush();
        }
    }

    public function flush(): int
    {
        if ($this->buffer !== []) {
            Food::upsert($this->buffer, ['source', 'external_id'], $this->updatableColumns());
            $this->imported += count($this->buffer);
            $this->buffer = [];
        }

        return $this->imported;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $defaults = array_fill_keys($this->updatableColumns(), null);

        return [...$defaults, ...$attributes, 'source' => $this->source->value];
    }

    /**
     * @return list<string>
     */
    private function updatableColumns(): array
    {
        return [
            'barcode', 'name', 'brand', 'kcal', 'protein', 'carbs', 'fat', 'fiber', 'sugar', 'serving_size_g',
            ...Nutrients::MICROS,
        ];
    }
}
