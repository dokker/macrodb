<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFoodAliasRequest;
use App\Models\Food;
use App\Services\FoodCatalog;
use Illuminate\Http\JsonResponse;

class FoodAliasController extends Controller
{
    public function store(StoreFoodAliasRequest $request, Food $food, FoodCatalog $catalog): JsonResponse
    {
        $alias = $catalog->addAlias($food, $request->validated('name'), $request->validated('lang') ?? 'hu');

        return response()->json(['data' => ['food_id' => $food->id, 'name' => $alias->name, 'lang' => $alias->lang]], $alias->wasRecentlyCreated ? 201 : 200);
    }
}
