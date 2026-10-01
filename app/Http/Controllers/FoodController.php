<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFoodRequest;
use App\Http\Requests\UpdateFoodRequest;
use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Services\FoodCatalog;
use App\Services\FoodSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodController extends Controller
{
    public function index(Request $request, FoodSearch $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'limit' => ['integer', 'between:1,50'],
        ]);

        return response()->json([
            'data' => $search->search($validated['q'], (int) ($validated['limit'] ?? 10))
                ->map->toArray()
                ->all(),
        ]);
    }

    public function barcode(string $code, FoodCatalog $catalog): FoodResource|JsonResponse
    {
        $food = $catalog->findByBarcode($code);

        return $food === null
            ? response()->json(['message' => 'Nincs találat.'], 404)
            : new FoodResource($food);
    }

    public function store(StoreFoodRequest $request, FoodCatalog $catalog): JsonResponse
    {
        $food = $catalog->createFood($request->validated());

        return (new FoodResource($food))->response()->setStatusCode(201);
    }

    public function update(UpdateFoodRequest $request, Food $food, FoodCatalog $catalog): FoodResource
    {
        return new FoodResource($catalog->renameFood($food, $request->validated('display_name')));
    }
}
