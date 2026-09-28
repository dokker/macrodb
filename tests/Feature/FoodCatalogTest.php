<?php

use App\Enums\FoodSource;
use App\Models\Food;
use App\Services\FoodCatalog;
use Illuminate\Support\Facades\Http;

function offProduct(array $overrides = []): array
{
    return ['status' => 1, 'product' => [
        'code' => '5990000000001',
        'product_name' => 'Oat drink',
        'product_name_hu' => 'Zabital',
        'brands' => 'Oatly, Other',
        'serving_quantity' => 250,
        'nutriments' => ['energy-kcal_100g' => 46, 'proteins_100g' => 1, 'carbohydrates_100g' => 6.6, 'fat_100g' => 1.5, 'sugars_100g' => 4, 'sodium_100g' => 0.1, 'calcium_100g' => 0.12, 'vitamin-d_100g' => 0.0000015, 'saturated-fat_100g' => 0.2],
        ...$overrides,
    ]];
}

it('creates a custom food', function () {
    $food = app(FoodCatalog::class)->createFood(['name' => 'Házi lekvár', 'kcal' => 250, 'protein' => 0.5, 'carbs' => 60, 'fat' => 0.1]);

    expect($food->source)->toBe(FoodSource::Custom)
        ->and($food->fresh()->fiber)->toBeNull();
});

it('finds a barcode locally without calling Open Food Facts', function () {
    Http::fake();
    $food = Food::create(['source' => FoodSource::Custom, 'name' => 'Helyi', 'barcode' => '123', 'kcal' => 1, 'protein' => 1, 'carbs' => 1, 'fat' => 1]);

    expect(app(FoodCatalog::class)->findByBarcode('123')->is($food))->toBeTrue();
    Http::assertNothingSent();
});

it('persists a product fetched from Open Food Facts on a local miss', function () {
    Http::fake(['*/api/v2/product/5990000000001.json*' => Http::response(offProduct())]);

    $food = app(FoodCatalog::class)->findByBarcode('5990000000001');

    expect($food->source)->toBe(FoodSource::OpenFoodFacts)
        ->and($food->name)->toBe('Zabital')
        ->and($food->brand)->toBe('Oatly')
        ->and($food->kcal)->toBe(46.0)
        ->and($food->fiber)->toBeNull()
        ->and($food->serving_size_g)->toBe(250.0)
        ->and($food->sodium_mg)->toBe(100.0)
        ->and($food->calcium_mg)->toBe(120.0)
        ->and($food->vitamin_d_ug)->toBe(1.5)
        ->and($food->saturated_fat_g)->toBe(0.2)
        ->and($food->iron_mg)->toBeNull();

    app(FoodCatalog::class)->findByBarcode('5990000000001');
    Http::assertSentCount(1);
});

it('derives sodium from salt when OFF gives no sodium value', function () {
    Http::fake(['*' => Http::response(offProduct(['nutriments' => ['energy-kcal_100g' => 1, 'proteins_100g' => 1, 'carbohydrates_100g' => 1, 'fat_100g' => 1, 'salt_100g' => 2.5]]))]);

    expect(app(FoodCatalog::class)->findByBarcode('5990000000001')->sodium_mg)->toBe(1000.0);
});

it('returns null for unknown or unusable Open Food Facts products', function (mixed $response) {
    Http::fake(['*' => $response]);

    expect(app(FoodCatalog::class)->findByBarcode('999'))->toBeNull()
        ->and(Food::count())->toBe(0);
})->with([
    'not found' => fn () => Http::response(['status' => 0]),
    'server error' => fn () => Http::response('', 500),
    'missing macros' => fn () => Http::response(offProduct(['nutriments' => ['energy-kcal_100g' => 10]])),
    'missing name' => fn () => Http::response(offProduct(['product_name' => '', 'product_name_hu' => ''])),
]);

it('creates a recipe whose per-100 g values use the cooked weight', function () {
    $flour = foodPer100g('Liszt', kcal: 360, protein: 10, carbs: 75, fat: 1);

    $recipe = app(FoodCatalog::class)->createRecipe(
        'Palacsinta', 200, [['food_id' => $flour->id, 'grams' => 100]],
        defaultPortionG: 100, portions: [['label' => '1 db', 'grams' => 50]],
    );

    expect($recipe->nutrientsPer100g()->kcal)->toBe(180.0)
        ->and($recipe->portions)->toHaveCount(1);
});

it('rejects a recipe without ingredients or weight', function (array $ingredients, float $weight) {
    expect(fn () => app(FoodCatalog::class)->createRecipe('X', $weight, $ingredients))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'no ingredients' => [[], 100.0],
    'zero weight' => [[['food_id' => 1, 'grams' => 10]], 0.0],
]);
