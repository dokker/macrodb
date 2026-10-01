<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFoodAliasRequest;
use App\Http\Requests\UpdateFoodAliasRequest;
use App\Http\Resources\FoodAliasResource;
use App\Models\Food;
use App\Models\FoodAlias;
use App\Services\FoodCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FoodAliasController extends Controller
{
    public function index(Food $food): AnonymousResourceCollection
    {
        return FoodAliasResource::collection($food->aliases()->orderBy('name')->get());
    }

    public function store(StoreFoodAliasRequest $request, Food $food, FoodCatalog $catalog): JsonResponse
    {
        $alias = $catalog->addAlias($food, $request->validated('name'), $request->validated('lang') ?? 'hu');

        return (new FoodAliasResource($alias))->response()->setStatusCode($alias->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateFoodAliasRequest $request, Food $food, FoodAlias $alias, FoodCatalog $catalog): FoodAliasResource
    {
        return new FoodAliasResource($catalog->renameAlias($alias, $request->validated('name')));
    }

    public function destroy(Food $food, FoodAlias $alias, FoodCatalog $catalog): Response
    {
        $catalog->deleteAlias($alias);

        return response()->noContent();
    }
}
