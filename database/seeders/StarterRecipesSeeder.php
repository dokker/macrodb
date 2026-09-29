<?php

namespace Database\Seeders;

use App\Enums\FoodSource;
use App\Models\Food;
use App\Models\Recipe;
use App\Services\FoodCatalog;
use Illuminate\Database\Seeder;

class StarterRecipesSeeder extends Seeder
{
    /**
     * USDA foods (by FDC id) the starter recipes are built from, with their Hungarian search names.
     *
     * @var array<string, list<string>>
     */
    private const array INGREDIENTS = [
        '2346396' => ['zabpehely', 'zab'],
        '324860' => ['mogyoróvaj'],
        '321359' => ['tej', 'tej 2,8%'],
        '2257046' => ['zabtej', 'zabital'],
        '2710375' => ['kávé', 'főzött kávé'],
        '168877' => ['rizs', 'fehér rizs'],
        '170016' => ['zöldborsó', 'borsó', 'fagyasztott zöldborsó'],
        '173410' => ['vaj'],
        '171025' => ['napraforgóolaj', 'olaj'],
        '173468' => ['só'],
        '171077' => ['csirkemell', 'csirkemellfilé'],
        '171060' => ['csirkemáj', 'máj'],
        '171287' => ['tojás'],
        '168936' => ['liszt', 'búzaliszt', 'fehér liszt'],
        '174928' => ['zsemlemorzsa'],
        '174277' => ['szójaszósz'],
        '170005' => ['újhagyma', 'kínai hagyma', 'zöldhagyma'],
        '169230' => ['fokhagyma'],
    ];

    /**
     * Draft recipes: raw ingredient grams per FDC id, the finished weight and named portions. The grams and cooked
     * weights are estimates that need a review, since the finished weight sets every per-100 g value.
     *
     * @var list<array{name: string, total_weight_g: int, default_portion_g: int, ingredients: array<string, int>, portions: list<array{label: string, grams: int}>}>
     */
    private const array RECIPES = [
        [
            'name' => 'Zabkása mogyoróvajjal',
            'total_weight_g' => 260,
            'default_portion_g' => 260,
            'ingredients' => ['2346396' => 50, '321359' => 200, '324860' => 20],
            'portions' => [['label' => '1 tányér', 'grams' => 260]],
        ],
        [
            'name' => 'Kávé zabtejjel',
            'total_weight_g' => 300,
            'default_portion_g' => 300,
            'ingredients' => ['2710375' => 200, '2257046' => 100],
            'portions' => [['label' => '1 bögre', 'grams' => 300]],
        ],
        [
            'name' => 'Rizibizi',
            'total_weight_g' => 780,
            'default_portion_g' => 260,
            'ingredients' => ['168877' => 200, '170016' => 200, '173410' => 20, '171025' => 10, '173468' => 3],
            'portions' => [['label' => '1 tányér', 'grams' => 260], ['label' => '1 kis adag', 'grams' => 180]],
        ],
        [
            'name' => 'Kínai hagymás csirke',
            'total_weight_g' => 450,
            'default_portion_g' => 225,
            'ingredients' => ['171077' => 400, '170005' => 150, '171025' => 20, '174277' => 30, '169230' => 10],
            'portions' => [['label' => '1 adag', 'grams' => 225]],
        ],
        [
            'name' => 'Rántott csirkemáj',
            'total_weight_g' => 470,
            'default_portion_g' => 235,
            'ingredients' => ['171060' => 400, '168936' => 40, '171287' => 50, '174928' => 60, '171025' => 40, '173468' => 2],
            'portions' => [['label' => '1 adag', 'grams' => 235]],
        ],
    ];

    /**
     * Adds the Hungarian aliases and the starter recipes. Safe to re-run; a recipe whose ingredients are not imported
     * yet (`usda:import` first) is skipped with a warning.
     */
    public function run(FoodCatalog $catalog): void
    {
        $foods = Food::where('source', FoodSource::Usda)
            ->whereIn('external_id', array_keys(self::INGREDIENTS))
            ->get()
            ->keyBy('external_id');

        foreach ($foods as $externalId => $food) {
            foreach (self::INGREDIENTS[$externalId] as $alias) {
                $catalog->addAlias($food, $alias);
            }
        }

        foreach (self::RECIPES as $recipe) {
            $missing = collect(array_keys($recipe['ingredients']))->diff($foods->keys());

            if ($missing->isNotEmpty()) {
                $this->command?->warn("Skipped {$recipe['name']}: USDA foods {$missing->implode(', ')} are not imported.");

                continue;
            }

            if (Recipe::where('name', $recipe['name'])->exists()) {
                continue;
            }

            $catalog->createRecipe(
                $recipe['name'],
                $recipe['total_weight_g'],
                collect($recipe['ingredients'])
                    ->map(fn (int $grams, string $externalId): array => ['food_id' => $foods[$externalId]->id, 'grams' => $grams])
                    ->values()
                    ->all(),
                $recipe['default_portion_g'],
                portions: $recipe['portions'],
            );
        }
    }
}
