<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyTargetRequest;
use App\Models\DailyTarget;
use App\Services\DailySummary;
use App\Services\DailyTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class DailyTargetController extends Controller
{
    public function index(DailyTargets $targets): JsonResponse
    {
        $current = $targets->forDate(Carbon::now(DailySummary::TIMEZONE)->toDateString());

        return response()->json(['data' => [
            'current' => $current === null ? null : $targets->toArray($current),
            'history' => $targets->all()->map(fn (DailyTarget $target): array => $targets->toArray($target))->all(),
        ]]);
    }

    public function store(StoreDailyTargetRequest $request, DailyTargets $targets): JsonResponse
    {
        $data = $request->validated();

        $target = $targets->set($data['valid_from'], (int) $data['kcal'], (float) $data['protein'], (float) $data['carbs'], (float) $data['fat']);

        return response()->json(['data' => $targets->toArray($target)], $target->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(DailyTarget $dailyTarget): Response
    {
        $dailyTarget->delete();

        return response()->noContent();
    }
}
