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
        return (new RecipeResource($catalog->createRecipeFromInput($request->validated())))->response()->setStatusCode(201);
    }
}
