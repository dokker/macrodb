<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Services\FoodCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a recipe or dish from ingredients in grams. total_weight_g is the weight of the finished dish (water is lost when cooking); per-100 g values are computed from it. Optional: without it the sum of the ingredient grams is used.')]
class CreateRecipeTool extends Tool
{
    public function handle(Request $request, FoodCatalog $catalog): Response
    {
        $data = $request->validate((new StoreRecipeRequest)->rules());

        return Response::json((new RecipeResource($catalog->createRecipeFromInput($data)))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'total_weight_g' => $schema->number()->description('Weight of the finished dish; defaults to the sum of the ingredients'),
            'default_portion_g' => $schema->number(),
            'is_dish' => $schema->boolean(),
            'ingredients' => $schema->array()->items($schema->object([
                'food_id' => $schema->integer()->required(),
                'grams' => $schema->number()->required(),
            ]))->required(),
            'portions' => $schema->array()->items($schema->object([
                'label' => $schema->string()->required(),
                'grams' => $schema->number()->required(),
            ])),
        ];
    }
}
