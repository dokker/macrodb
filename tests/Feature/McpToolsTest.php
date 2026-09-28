<?php

use App\Mcp\Servers\MacroDbServer;
use App\Mcp\Tools\CreateFoodTool;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\DeleteMealItemTool;
use App\Mcp\Tools\GetDailySummaryTool;
use App\Mcp\Tools\GetFoodByBarcodeTool;
use App\Mcp\Tools\GetRangeSummaryTool;
use App\Mcp\Tools\LogMealTool;
use App\Mcp\Tools\SearchFoodsTool;
use App\Mcp\Tools\UpdateMealItemTool;
use App\Models\MealItem;
use Illuminate\Support\Facades\Http;

it('searches foods', function () {
    foodPer100g('Zabpehely', kcal: 370);

    MacroDbServer::tool(SearchFoodsTool::class, ['query' => 'zab'])
        ->assertOk()
        ->assertSee('Zabpehely');

    MacroDbServer::tool(SearchFoodsTool::class, [])->assertHasErrors();
});

it('reports a barcode miss as an error', function () {
    Http::fake(['*' => Http::response(['status' => 0])]);

    MacroDbServer::tool(GetFoodByBarcodeTool::class, ['barcode' => '000'])->assertHasErrors(['Nincs találat.']);
});

it('logs, updates and deletes through the same service layer', function () {
    $rice = foodPer100g('Rizs', kcal: 130);

    MacroDbServer::tool(LogMealTool::class, [
        'eaten_at' => '2026-09-28T12:00:00+02:00',
        'meal_type' => 'ebéd',
        'items' => [['food_id' => $rice->id, 'grams' => 100]],
    ])->assertOk()->assertSee('130');

    $item = MealItem::firstOrFail();

    MacroDbServer::tool(UpdateMealItemTool::class, ['item_id' => $item->id, 'grams' => 200])->assertSee('260');
    MacroDbServer::tool(GetDailySummaryTool::class, ['date' => '2026-09-28'])->assertSee('260');
    MacroDbServer::tool(GetRangeSummaryTool::class, ['from' => '2026-09-28', 'to' => '2026-09-28'])->assertSee('260');
    MacroDbServer::tool(DeleteMealItemTool::class, ['item_id' => $item->id])->assertSee('Törölve');
    MacroDbServer::tool(DeleteMealItemTool::class, ['item_id' => $item->id])->assertHasErrors();
});

it('rejects an invalid meal', function () {
    MacroDbServer::tool(LogMealTool::class, [
        'eaten_at' => '2026-09-28T12:00:00+02:00',
        'meal_type' => 'ebéd',
        'items' => [['grams' => 100]],
    ])->assertHasErrors();
});

it('creates foods and recipes', function () {
    MacroDbServer::tool(CreateFoodTool::class, ['name' => 'Lekvár', 'kcal' => 250, 'protein' => 0, 'carbs' => 60, 'fat' => 0])
        ->assertOk()->assertSee('Lekvár');

    $flour = foodPer100g('Liszt', kcal: 360);

    MacroDbServer::tool(CreateRecipeTool::class, [
        'name' => 'Palacsinta',
        'total_weight_g' => 200,
        'ingredients' => [['food_id' => $flour->id, 'grams' => 100]],
    ])->assertOk()->assertSee('180');
});
