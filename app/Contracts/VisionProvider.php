<?php

namespace App\Contracts;

use App\Services\Vision\DetectedItem;

/**
 * Names the foods on a photo and estimates their weight. It never estimates nutrients.
 */
interface VisionProvider
{
    /**
     * @return list<DetectedItem>
     */
    public function identify(string $imageBytes, string $mimeType, ?string $note = null): array;
}
