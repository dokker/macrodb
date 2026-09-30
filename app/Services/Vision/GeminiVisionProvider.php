<?php

namespace App\Services\Vision;

use App\Contracts\VisionProvider;
use Illuminate\Support\Facades\Http;

/**
 * Gemini adapter over the Interactions API (free tier: submitted images may be used by Google to improve its products, which the spec accepts).
 */
class GeminiVisionProvider implements VisionProvider
{
    private const string PROMPT = <<<'TXT'
You see a photo of a meal. List every distinct food or drink item visible.
For each item give its Hungarian name (name_hu), its English name (name_en), your estimate of the eaten weight in grams (grams) and your confidence from 0 to 1 (confidence).
Use simple generic food names, not brands. Do not estimate calories or nutrients. If the user note changes the amount or preparation (for example "half portion"), take it into account.
TXT;

    /**
     * @return list<DetectedItem>
     */
    public function identify(string $imageBytes, string $mimeType, ?string $note = null): array
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new VisionException('GEMINI_API_KEY is not configured.');
        }

        $prompt = self::PROMPT.($note !== null && $note !== '' ? "\nUser note: {$note}" : '');

        $response = Http::baseUrl(config('services.gemini.base_url'))
            ->withHeader('x-goog-api-key', $key)
            ->timeout(45)
            ->retry(2, 1500, fn ($exception) => in_array($exception->getCode(), [429, 503], true), throw: false)
            ->post('/v1beta/interactions', [
                'model' => config('services.gemini.model'),
                'store' => false,
                'input' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image', 'mime_type' => $mimeType, 'data' => base64_encode($imageBytes)],
                ],
                'response_format' => [
                    'type' => 'text',
                    'mime_type' => 'application/json',
                    'schema' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name_hu' => ['type' => 'string'],
                                'name_en' => ['type' => 'string'],
                                'grams' => ['type' => 'number'],
                                'confidence' => ['type' => 'number'],
                            ],
                            'required' => ['name_hu', 'name_en', 'grams', 'confidence'],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new VisionException("The vision model request failed ({$response->status()}).");
        }

        $answer = collect($response->json('steps', []))->firstWhere('type', 'model_output');
        $text = collect($answer['content'] ?? [])->firstWhere('type', 'text');
        $items = json_decode((string) ($text['text'] ?? ''), true);

        if (! is_array($items)) {
            throw new VisionException('The vision model returned an unreadable answer.');
        }

        $detected = [];

        foreach ($items as $item) {
            if (
                is_array($item)
                && is_string($item['name_hu'] ?? null) && trim($item['name_hu']) !== ''
                && is_numeric($item['grams'] ?? null) && $item['grams'] > 0
            ) {
                $detected[] = new DetectedItem(
                    trim($item['name_hu']),
                    is_string($item['name_en'] ?? null) ? trim($item['name_en']) : '',
                    (float) $item['grams'],
                    is_numeric($item['confidence'] ?? null) ? min(1.0, max(0.0, (float) $item['confidence'])) : 0.0,
                );
            }
        }

        return $detected;
    }
}
