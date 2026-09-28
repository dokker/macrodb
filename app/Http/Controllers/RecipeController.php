<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Services\FoodCatalog;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
    public function store(StoreRecipeRequest $request, FoodCatalog $catalog): JsonResponse
    {
        $data = $request->validated();

        $recipe = $catalog->createRecipe(
            $data['name'],
            (float) $data['total_weight_g'],
            $data['ingredients'],
            isset($data['default_portion_g']) ? (float) $data['default_portion_g'] : null,
            $data['is_dish'] ?? true,
            $data['portions'] ?? [],
        );

        return (new RecipeResource($recipe))->response()->setStatusCode(201);
    }
}
