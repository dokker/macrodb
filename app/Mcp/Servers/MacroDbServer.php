<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddFoodAliasTool;
use App\Mcp\Tools\CreateFoodTool;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\DeleteMealItemTool;
use App\Mcp\Tools\GetDailySummaryTool;
use App\Mcp\Tools\GetFoodByBarcodeTool;
use App\Mcp\Tools\GetRangeSummaryTool;
use App\Mcp\Tools\LogMealTool;
use App\Mcp\Tools\SearchFoodsTool;
use App\Mcp\Tools\UpdateMealItemTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('MacroDB')]
#[Version('1.0.0')]
#[Instructions('Personal nutrition log. Search foods with search_foods, convert portions and counts to grams using the returned portions, then record everything with a single log_meal call. The backend computes all macros; never estimate them yourself. If a food is only found under an English name, tell the user; if nothing matches, use create_food.')]
class MacroDbServer extends Server
{
    protected array $tools = [
        SearchFoodsTool::class,
        GetFoodByBarcodeTool::class,
        LogMealTool::class,
        UpdateMealItemTool::class,
        DeleteMealItemTool::class,
        GetDailySummaryTool::class,
        GetRangeSummaryTool::class,
        CreateFoodTool::class,
        CreateRecipeTool::class,
        AddFoodAliasTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
