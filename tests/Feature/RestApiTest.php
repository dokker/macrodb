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

it('uses the sum of the ingredients as the finished weight when none is given', function () {
    $flour = foodPer100g('Liszt', kcal: 360);
    $milk = foodPer100g('Tej', kcal: 60);

    $this->postJson('/api/recipes', [
        'name' => 'Palacsinta',
        'ingredients' => [['food_id' => $flour->id, 'grams' => 100], ['food_id' => $milk->id, 'grams' => 100]],
    ])->assertCreated()->assertJsonPath('data.total_weight_g', 200)->assertJsonPath('data.per_100g.kcal', 210);
});

it('lists and shows recipes with their ingredients', function () {
    $flour = foodPer100g('Liszt', kcal: 360);

    $recipeId = $this->postJson('/api/recipes', [
        'name' => 'Palacsinta',
        'ingredients' => [['food_id' => $flour->id, 'grams' => 100]],
    ])->json('data.id');

    $this->getJson('/api/recipes')->assertOk()->assertJsonPath('data.0.name', 'Palacsinta');
    $this->getJson("/api/recipes/{$recipeId}")
        ->assertOk()
        ->assertJsonPath('data.ingredients.0.name', 'Liszt')
        ->assertJsonPath('data.ingredient_total_g', 100);
});

it('updates a recipe, replacing ingredients and defaulting the weight to their sum', function () {
    $flour = foodPer100g('Liszt', kcal: 360);
    $milk = foodPer100g('Tej', kcal: 60);

    $recipeId = $this->postJson('/api/recipes', [
        'name' => 'Palacsinta',
        'total_weight_g' => 150,
        'ingredients' => [['food_id' => $flour->id, 'grams' => 100]],
        'portions' => [['label' => '1 db', 'grams' => 50]],
    ])->json('data.id');

    $this->putJson("/api/recipes/{$recipeId}", [
        'name' => 'Palacsinta tejjel',
        'ingredients' => [['food_id' => $flour->id, 'grams' => 100], ['food_id' => $milk->id, 'grams' => 100]],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Palacsinta tejjel')
        ->assertJsonPath('data.total_weight_g', 200)
        ->assertJsonPath('data.per_100g.kcal', 210)
        ->assertJsonCount(2, 'data.ingredients')
        ->assertJsonCount(0, 'data.portions');
});

it('deletes a recipe that was never logged and refuses one that was', function () {
    $flour = foodPer100g('Liszt', kcal: 360);
    $ingredients = [['food_id' => $flour->id, 'grams' => 100]];

    $unused = $this->postJson('/api/recipes', ['name' => 'Egy', 'ingredients' => $ingredients])->json('data.id');
    $logged = $this->postJson('/api/recipes', ['name' => 'Kettő', 'ingredients' => $ingredients])->json('data.id');

    $this->postJson('/api/meals', [
        'eaten_at' => '2026-09-28T12:00:00+02:00',
        'meal_type' => 'ebéd',
        'items' => [['recipe_id' => $logged, 'grams' => 100, 'input_method' => 'szöveg']],
    ])->assertCreated();

    $this->deleteJson("/api/recipes/{$unused}")->assertNoContent();
    $this->deleteJson("/api/recipes/{$logged}")->assertStatus(409);
    $this->assertDatabaseMissing('recipes', ['id' => $unused]);
    $this->assertDatabaseHas('recipes', ['id' => $logged]);
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
