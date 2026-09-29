<?php

use App\Enums\FoodSource;
use App\Models\Food;
use App\Models\Recipe;
use App\Services\FoodSearch;
use Database\Seeders\StarterRecipesSeeder;

function importStarterIngredients(array $except = []): void
{
    $ids = ['2346396', '324860', '321359', '2257046', '2710375', '168877', '170016', '173410', '171025', '173468', '171077', '171060', '171287', '168936', '174928', '174277', '170005', '169230'];

    foreach (array_diff($ids, $except) as $id) {
        Food::create(['source' => FoodSource::Usda, 'external_id' => $id, 'name' => "USDA {$id}", 'kcal' => 100, 'protein' => 10, 'carbs' => 10, 'fat' => 5]);
    }
}

it('seeds the starter recipes and Hungarian aliases from imported USDA foods', function () {
    importStarterIngredients();

    $this->seed(StarterRecipesSeeder::class);

    expect(Recipe::pluck('name')->all())->toContain('Rizibizi', 'Rántott csirkemáj')
        ->and(Recipe::count())->toBe(5)
        ->and(Recipe::where('name', 'Rizibizi')->sole()->portions)->toHaveCount(2);

    $names = app(FoodSearch::class)->search('zab')->pluck('name')->all();
    expect($names)->toContain('USDA 2346396');
});

it('is idempotent when re-run', function () {
    importStarterIngredients();

    $this->seed(StarterRecipesSeeder::class);
    $this->seed(StarterRecipesSeeder::class);

    expect(Recipe::count())->toBe(5);
});

it('skips recipes whose ingredients are not imported', function () {
    importStarterIngredients(except: ['171060']);

    $this->seed(StarterRecipesSeeder::class);

    expect(Recipe::where('name', 'Rántott csirkemáj')->exists())->toBeFalse()
        ->and(Recipe::count())->toBe(4);
});
