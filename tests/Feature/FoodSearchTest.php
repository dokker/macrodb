<?php

use App\Enums\FoodSource;
use App\Models\Food;
use App\Models\Recipe;
use App\Services\FoodSearch;

/**
 * @param  list<string>  $aliases
 */
function makeFood(string $name, array $aliases = [], FoodSource $source = FoodSource::OpenFoodFacts, array $per100g = []): Food
{
    $food = Food::create([
        'source' => $source,
        'name' => $name,
        ...['kcal' => 100, 'protein' => 1, 'carbs' => 1, 'fat' => 1],
        ...$per100g,
    ]);

    foreach ($aliases as $alias) {
        $food->aliases()->create(['name' => $alias, 'lang' => 'hu']);
    }

    return $food;
}

/**
 * @return list<string>
 */
function searchNames(string $query, int $limit = 10): array
{
    return app(FoodSearch::class)->search($query, $limit)->pluck('name')->all();
}

beforeEach(function () {
    makeFood('Oats', aliases: ['zabpehely'], source: FoodSource::Usda);
    makeFood('Zabpehely finomra vágott');
    makeFood('Zabtej');
    makeFood('Tej 2,8%');
    makeFood('Rizs');
    makeFood('Rántott csirkemáj');
});

it('finds Hungarian partial words, including English foods through their alias', function () {
    expect(searchNames('zab'))
        ->toContain('Oats', 'Zabpehely finomra vágott', 'Zabtej')
        ->not->toContain('Rizs', 'Tej 2,8%');
});

it('finds a word inside a Hungarian compound', function () {
    expect(searchNames('tej'))->toContain('Zabtej', 'Tej 2,8%');
});

it('ranks names starting with the query ahead of names that only contain it', function () {
    expect(searchNames('tej'))->toBe(['Tej 2,8%', 'Zabtej']);
});

it('ignores case and accents', function () {
    expect(searchNames('RANTOTT'))->toBe(['Rántott csirkemáj']);
});

it('requires every word of a multi-word query', function () {
    expect(searchNames('zab vágott'))->toBe(['Zabpehely finomra vágott']);
});

it('treats LIKE wildcards in the query literally', function () {
    expect(searchNames('%'))->toBe(['Tej 2,8%'])
        ->and(searchNames('_'))->toBe([]);
});

it('returns recipes alongside foods with macros from the cooked weight', function () {
    $oats = Food::where('name', 'Oats')->sole();
    $oats->update(['kcal' => 380, 'protein' => 13, 'carbs' => 60, 'fat' => 7]);

    $porridge = Recipe::create(['name' => 'Zabkása mogyoróvajjal', 'total_weight_g' => 200, 'default_portion_g' => 200, 'is_dish' => true]);
    $porridge->items()->create(['food_id' => $oats->id, 'grams' => 50]);
    $porridge->portions()->create(['label' => '1 tál', 'grams' => 200]);

    $result = app(FoodSearch::class)->search('zabkása')->sole();

    expect($result->toArray())->toMatchArray([
        'type' => 'recipe',
        'name' => 'Zabkása mogyoróvajjal',
        'per_100g' => ['kcal' => 95.0, 'protein' => 3.3, 'carbs' => 15.0, 'fat' => 1.8, 'fiber' => null, 'sugar' => null],
        'default_portion_g' => 200.0,
        'portions' => [['label' => '1 tál', 'grams' => 200.0]],
    ]);
});

it('returns nothing for a blank query', function () {
    expect(searchNames('   '))->toBe([]);
});

it('respects the limit', function () {
    expect(searchNames('zab', limit: 2))->toHaveCount(2);
});
