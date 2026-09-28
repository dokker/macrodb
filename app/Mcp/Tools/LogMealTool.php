<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreMealRequest;
use App\Http\Resources\MealResource;
use App\Services\MealLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Log a meal. Every item is a food_id or recipe_id from search_foods plus grams (convert portions to grams first). The backend computes the macros; never estimate them.')]
class LogMealTool extends Tool
{
    public function handle(Request $request, MealLogger $logger): Response
    {
        $data = $request->validate((new StoreMealRequest)->rules());

        return Response::json((new MealResource($logger->logFromInput($data)))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'eaten_at' => $schema->string()->description('ISO 8601 date-time with offset, e.g. 2026-09-28T08:30:00+02:00')->required(),
            'meal_type' => $schema->string()->enum(['reggeli', 'ebéd', 'vacsora', 'snack'])->required(),
            'note' => $schema->string(),
            'items' => $schema->array()->items($schema->object([
                'food_id' => $schema->integer()->description('Give either food_id or recipe_id'),
                'recipe_id' => $schema->integer(),
                'grams' => $schema->number()->description('Eaten amount in grams (ml for liquids)')->required(),
                'source_text' => $schema->string()->description("The user's own words for this item"),
                'input_method' => $schema->string()->enum(['szöveg', 'vonalkód', 'fotó']),
            ]))->required(),
        ];
    }
}
