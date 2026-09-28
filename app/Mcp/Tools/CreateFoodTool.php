<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreFoodRequest;
use App\Http\Resources\FoodResource;
use App\Services\FoodCatalog;
use App\Support\Nutrients;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a custom food that search_foods could not find. Nutrients are per 100 g (per 100 ml for liquids); fiber and sugar may be omitted when unknown.')]
class CreateFoodTool extends Tool
{
    public function handle(Request $request, FoodCatalog $catalog): Response
    {
        $data = $request->validate((new StoreFoodRequest)->rules());

        return Response::json((new FoodResource($catalog->createFood($data)))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'brand' => $schema->string(),
            'barcode' => $schema->string(),
            'kcal' => $schema->number()->description('per 100 g')->required(),
            'protein' => $schema->number()->required(),
            'carbs' => $schema->number()->required(),
            'fat' => $schema->number()->required(),
            'fiber' => $schema->number(),
            'sugar' => $schema->number(),
            'serving_size_g' => $schema->number()->description('Typical serving in grams'),
            ...array_map(
                fn (): Type => $schema->number()->description('Optional micronutrient per 100 g; omit when unknown'),
                array_flip(Nutrients::MICROS),
            ),
        ];
    }
}
