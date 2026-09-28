<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMealItemRequest;
use App\Http\Resources\MealResource;
use App\Models\MealItem;
use App\Services\MealLogger;
use Illuminate\Http\Response;

class MealItemController extends Controller
{
    public function update(UpdateMealItemRequest $request, MealItem $mealItem, MealLogger $logger): MealResource
    {
        return new MealResource($logger->updateItemGrams($mealItem, (float) $request->validated('grams')));
    }

    public function destroy(MealItem $mealItem, MealLogger $logger): MealResource|Response
    {
        $meal = $logger->deleteItem($mealItem);

        return $meal === null ? response()->noContent() : new MealResource($meal);
    }
}
