<?php

use App\Contracts\VisionProvider;
use App\Models\Meal;
use App\Models\User;
use App\Services\Vision\DetectedItem;
use App\Services\Vision\GeminiVisionProvider;
use App\Services\Vision\VisionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

function fakeVision(array $items): void
{
    app()->instance(VisionProvider::class, new class($items) implements VisionProvider
    {
        public function __construct(private array $items) {}

        public function identify(string $imageBytes, string $mimeType, ?string $note = null): array
        {
            return $this->items;
        }
    });
}

function geminiAnswer(array $items): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => json_encode($items)]]]]]];
}

beforeEach(function () {
    Passport::actingAs(User::factory()->create());
    config(['services.gemini.key' => 'test-key']);
});

it('returns an unsaved draft with matched foods and alternatives', function () {
    foodPer100g('Zabpehely', kcal: 370);
    $milk = foodPer100g('Zabtej', kcal: 45);
    fakeVision([
        new DetectedItem('zab', 'oats', 60, 0.9),
        new DetectedItem('unikornis', 'unicorn', 100, 0.2),
    ]);

    $this->post('/api/meals/photo', ['image' => UploadedFile::fake()->image('meal.jpg'), 'note' => 'fél adag'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.items.0.food_id', $milk->id)
        ->assertJsonPath('data.items.0.grams', 60)
        ->assertJsonPath('data.items.0.input_method', 'fotó')
        ->assertJsonCount(1, 'data.items.0.alternatives')
        ->assertJsonPath('data.items.1.food_id', null)
        ->assertJsonPath('data.items.1.match', null);

    expect(Meal::count())->toBe(0);
});

it('falls back to the English name when the Hungarian one finds nothing', function () {
    $oats = foodPer100g('Oats', kcal: 380);
    fakeVision([new DetectedItem('valami', 'oats', 50, 0.5)]);

    $this->post('/api/meals/photo', ['image' => UploadedFile::fake()->image('meal.png')], ['Accept' => 'application/json'])
        ->assertJsonPath('data.items.0.food_id', $oats->id);
});

it('rejects a missing or non-image upload', function (array $payload) {
    $this->post('/api/meals/photo', $payload, ['Accept' => 'application/json'])->assertUnprocessable();
})->with([
    'no image' => [[]],
    'not an image' => [['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]],
]);

it('answers 502 when the vision provider fails', function () {
    app()->instance(VisionProvider::class, new class implements VisionProvider
    {
        public function identify(string $imageBytes, string $mimeType, ?string $note = null): array
        {
            throw new VisionException('down');
        }
    });

    $this->post('/api/meals/photo', ['image' => UploadedFile::fake()->image('meal.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(502);
});

it('parses the Gemini answer, sends the note and drops unusable items', function () {
    Http::fake(['*' => Http::response(geminiAnswer([
        ['name_hu' => 'rizs', 'name_en' => 'rice', 'grams' => 150, 'confidence' => 1.4],
        ['name_hu' => '', 'name_en' => 'x', 'grams' => 10, 'confidence' => 0.5],
        ['name_hu' => 'levegő', 'name_en' => 'air', 'grams' => 0, 'confidence' => 0.5],
    ]))]);

    $items = app(GeminiVisionProvider::class)->identify('bytes', 'image/jpeg', 'olajban sütve');

    expect($items)->toHaveCount(1)
        ->and($items[0]->nameHu)->toBe('rizs')
        ->and($items[0]->confidence)->toBe(1.0);

    Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-key')
        && str_contains($request['contents'][0]['parts'][0]['text'], 'olajban sütve')
        && $request['contents'][0]['parts'][1]['inline_data']['mime_type'] === 'image/jpeg');
});

it('fails clearly on Gemini errors and a missing key', function () {
    Http::fake(['*' => Http::response('', 500)]);
    expect(fn () => app(GeminiVisionProvider::class)->identify('b', 'image/png'))->toThrow(VisionException::class);

    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not json']]]]]])]);
    expect(fn () => app(GeminiVisionProvider::class)->identify('b', 'image/png'))->toThrow(VisionException::class);

    config(['services.gemini.key' => null]);
    expect(fn () => app(GeminiVisionProvider::class)->identify('b', 'image/png'))->toThrow(VisionException::class);
});
