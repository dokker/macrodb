<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreFoodAliasRequest;
use App\Models\Food;
use App\Services\FoodCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a search name (usually Hungarian) to a food, e.g. "zabpehely" for the USDA food "Oats", so later searches find it. Use it when search_foods only returned an English match.')]
class AddFoodAliasTool extends Tool
{
    public function handle(Request $request, FoodCatalog $catalog): Response
    {
        $data = $request->validate(['food_id' => ['required', 'integer'], ...(new StoreFoodAliasRequest)->rules()]);

        $food = Food::find($data['food_id']);

        if ($food === null) {
            return Response::error('Nincs ilyen élelmiszer.');
        }

        $alias = $catalog->addAlias($food, $data['name'], $data['lang'] ?? 'hu');

        return Response::json(['food_id' => $food->id, 'name' => $alias->name, 'lang' => $alias->lang]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'food_id' => $schema->integer()->required(),
            'name' => $schema->string()->description('The alias to add')->required(),
            'lang' => $schema->string()->description('Two-letter language code, default hu'),
        ];
    }
}
