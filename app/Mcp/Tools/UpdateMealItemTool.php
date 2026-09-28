<?php

namespace App\Mcp\Tools;

use App\Http\Requests\UpdateMealItemRequest;
use App\Http\Resources\MealResource;
use App\Models\MealItem;
use App\Services\MealLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Change the grams of a logged meal item. Returns the updated meal.')]
#[IsIdempotent]
class UpdateMealItemTool extends Tool
{
    public function handle(Request $request, MealLogger $logger): Response
    {
        $data = $request->validate(['item_id' => ['required', 'integer'], ...(new UpdateMealItemRequest)->rules()]);

        $item = MealItem::find($data['item_id']);

        return $item === null
            ? Response::error('Nincs ilyen étkezési tétel.')
            : Response::json((new MealResource($logger->updateItemGrams($item, (float) $data['grams'])))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('Meal item id from a summary or log_meal result')->required(),
            'grams' => $schema->number()->required(),
        ];
    }
}
