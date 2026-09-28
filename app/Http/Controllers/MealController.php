<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMealRequest;
use App\Http\Resources\MealResource;
use App\Services\MealLogger;
use Illuminate\Http\JsonResponse;

class MealController extends Controller
{
    public function store(StoreMealRequest $request, MealLogger $logger): JsonResponse
    {
        return (new MealResource($logger->logFromInput($request->validated())))->response()->setStatusCode(201);
    }
}
