<?php

namespace App\Http\Controllers;

use App\Exceptions\RecipeInUseException;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Services\FoodCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RecipeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RecipeResource::collection(Recipe::with('items.food', 'portions')->orderBy('name')->get());
    }

    public function show(Recipe $recipe): RecipeResource
    {
        return new RecipeResource($recipe->load('items.food', 'portions'));
    }

    public function store(StoreRecipeRequest $request, FoodCatalog $catalog): JsonResponse
    {
        return (new RecipeResource($catalog->createRecipeFromInput($request->validated())))->response()->setStatusCode(201);
    }

    public function update(StoreRecipeRequest $request, Recipe $recipe, FoodCatalog $catalog): RecipeResource
    {
        return new RecipeResource($catalog->updateRecipe($recipe, $request->validated()));
    }

    public function destroy(Recipe $recipe, FoodCatalog $catalog): Response|JsonResponse
    {
        try {
            $catalog->deleteRecipe($recipe);
        } catch (RecipeInUseException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->noContent();
    }
}
