<?php

namespace App\Http\Controllers;

use App\Enums\InputMethod;
use App\Enums\MealType;
use App\Http\Requests\StoreMealRequest;
use App\Http\Resources\MealResource;
use App\Services\MealLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class MealController extends Controller
{
    public function store(StoreMealRequest $request, MealLogger $logger): JsonResponse
    {
        $data = $request->validated();

        $meal = $logger->log(
            Carbon::parse($data['eaten_at']),
            MealType::from($data['meal_type']),
            array_map(fn (array $item): array => [
                ...$item,
                'input_method' => isset($item['input_method']) ? InputMethod::from($item['input_method']) : InputMethod::Text,
            ], $data['items']),
            $data['note'] ?? null,
        );

        return (new MealResource($meal))->response()->setStatusCode(201);
    }
}
