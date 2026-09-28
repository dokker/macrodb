<?php

namespace App\Services\Vision;

final readonly class DetectedItem
{
    public function __construct(
        public string $nameHu,
        public string $nameEn,
        public float $grams,
        public float $confidence,
    ) {}
}
