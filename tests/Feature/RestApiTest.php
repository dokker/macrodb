<?php

use App\Models\DailyTarget;
use App\Models\Meal;
use App\Models\MealItem;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(fn () => Passport::actingAs(User::factory()->create()));

it('searches foods and recipes through GET /api/foods', function () {
    foodPer100g('Zabpehely', kcal: 370);

    $this->getJson('/api/foods?q=zab')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Zabpehely')
        ->assertJsonPath('data.0.per_100g.kcal', 370);

    $this->getJson('/api/foods')->assertUnprocessable();
});

it('returns 404 for an unknown barcode', function () {
    Http::fake(['*' => Http::response(['status' => 0])]);

    $this->getJson('/api/foods/barcode/000')->assertNotFound();
});

it('creates a food and rejects a duplicate barcode', function () {
    $payload = ['name' => 'Túró Rudi', 'barcode' => '5991', 'kcal' => 400, 'protein' => 10, 'carbs' => 40, 'fat' => 20];

    $this->postJson('/api/foods', $payload)->assertCreated()->assertJsonPath('data.source', 'custom');
    $this->postJson('/api/foods', $payload)->assertJsonValidationErrors('barcode');
});

it('creates a recipe from ingredients', function () {
    $flour = foodPer100g('Liszt', kcal: 360);

    $this->postJson('/api/recipes', [
        'name' => 'Palacsinta',
        'total_weight_g' => 200,
        'ingredients' => [['food_id' => $flour->id, 'grams' => 100]],
    ])->assertCreated()->assertJsonPath('data.per_100g.kcal', 180);
});

it('logs, corrects and deletes meal items end to end', function () {
    $rice = foodPer100g('Rizs', kcal: 130);

    $response = $this->postJson('/api/meals', [
        'eaten_at' => '2026-09-28T12:00:00+02:00',
        'meal_type' => 'ebéd',
        'items' => [['food_id' => $rice->id, 'grams' => 100, 'source_text' => 'egy adag rizs', 'input_method' => 'szöveg']],
    ])->assertCreated()->assertJsonPath('data.total.kcal', 130);

    $itemId = $response->json('data.items.0.id');

    $this->patchJson("/api/meal-items/{$itemId}", ['grams' => 200])->assertOk()->assertJsonPath('data.total.kcal', 260);
    $this->getJson('/api/summary/daily?date=2026-09-28')->assertJsonPath('data.total.kcal', 260);

    $this->deleteJson("/api/meal-items/{$itemId}")->assertNoContent();
    expect(Meal::count())->toBe(0)->and(MealItem::count())->toBe(0);
});

it('validates meal items', function (array $item) {
    $this->postJson('/api/meals', ['eaten_at' => now()->toIso8601String(), 'meal_type' => 'snack', 'items' => [$item]])
        ->assertUnprocessable();
})->with([
    'no food or recipe' => [['grams' => 10]],
    'both food and recipe' => [['food_id' => 1, 'recipe_id' => 1, 'grams' => 10]],
    'unknown food' => [['food_id' => 999, 'grams' => 10]],
    'negative grams' => [['food_id' => 1, 'grams' => -5]],
]);

it('summarises a range with targets', function () {
    DailyTarget::create(['valid_from' => '2026-01-01', 'kcal' => 2000, 'protein' => 100, 'carbs' => 250, 'fat' => 70]);

    $this->getJson('/api/summary/range?from=2026-09-27&to=2026-09-28')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.deviation.kcal', -2000);

    $this->getJson('/api/summary/range?from=2026-09-28&to=2026-09-27')->assertUnprocessable();
});

it('adds an alias that makes an English food searchable and ignores duplicates', function () {
    $oats = foodPer100g('Oats', kcal: 380);

    $this->getJson('/api/foods?q=zabpehely')->assertJsonCount(0, 'data');

    $this->postJson("/api/foods/{$oats->id}/aliases", ['name' => 'zabpehely'])->assertCreated();
    $this->postJson("/api/foods/{$oats->id}/aliases", ['name' => 'zabpehely'])->assertOk();

    $this->getJson('/api/foods?q=zabpehely')->assertJsonPath('data.0.name', 'Oats');
    expect($oats->aliases()->count())->toBe(1);
});
