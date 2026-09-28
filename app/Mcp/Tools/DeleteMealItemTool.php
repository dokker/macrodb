<?php

namespace App\Mcp\Tools;

use App\Http\Resources\MealResource;
use App\Models\MealItem;
use App\Services\MealLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Delete a logged meal item; the meal disappears with its last item. Returns the remaining meal, or a confirmation when the meal is gone.')]
#[IsDestructive]
class DeleteMealItemTool extends Tool
{
    public function handle(Request $request, MealLogger $logger): Response
    {
        $data = $request->validate(['item_id' => ['required', 'integer']]);

        $item = MealItem::find($data['item_id']);

        if ($item === null) {
            return Response::error('Nincs ilyen étkezési tétel.');
        }

        $meal = $logger->deleteItem($item);

        return $meal === null
            ? Response::text('Törölve, az étkezés is megszűnt (nem maradt tétele).')
            : Response::json((new MealResource($meal))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['item_id' => $schema->integer()->required()];
    }
}
