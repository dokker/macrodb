<?php

namespace App\Http\Controllers;

use App\Services\DailySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function daily(Request $request, DailySummary $summary): JsonResponse
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $summary->forDate($validated['date'])]);
    }

    public function range(Request $request, DailySummary $summary): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => $summary->forRange($validated['from'], $validated['to'])]);
    }
}
