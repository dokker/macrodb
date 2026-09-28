<?php

namespace App\Services;

use App\Contracts\VisionProvider;
use App\Enums\InputMethod;
use App\Services\Vision\DetectedItem;

/**
 * Turns a photo into an unsaved draft: the vision model names items and estimates grams, the local search
 * finds the matching foods or recipes. Saving goes through the regular log_meal path after approval.
 */
class PhotoDraftBuilder
{
    private const int ALTERNATIVES = 3;

    public function __construct(private VisionProvider $vision, private FoodSearch $search) {}

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function build(string $imageBytes, string $mimeType, ?string $note = null): array
    {
        $items = array_map(function (DetectedItem $detected): array {
            $candidates = $this->search->search($detected->nameHu, self::ALTERNATIVES);

            if ($candidates->isEmpty() && $detected->nameEn !== '') {
                $candidates = $this->search->search($detected->nameEn, self::ALTERNATIVES);
            }

            $best = $candidates->first();

            return [
                'detected' => [
                    'name_hu' => $detected->nameHu,
                    'name_en' => $detected->nameEn,
                    'confidence' => $detected->confidence,
                ],
                'food_id' => $best?->type === 'food' ? $best->id : null,
                'recipe_id' => $best?->type === 'recipe' ? $best->id : null,
                'grams' => $detected->grams,
                'source_text' => $detected->nameHu,
                'input_method' => InputMethod::Photo->value,
                'match' => $best?->toArray(),
                'alternatives' => $candidates->skip(1)->map->toArray()->values()->all(),
            ];
        }, $this->vision->identify($imageBytes, $mimeType, $note));

        return ['items' => $items];
    }
}
