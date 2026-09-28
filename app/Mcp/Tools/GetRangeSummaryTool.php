<?php

namespace App\Mcp\Tools;

use App\Services\DailySummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('A list of daily summaries (totals, meals, target, deviation) for every day from `from` to `to`, inclusive.')]
#[IsReadOnly]
class GetRangeSummaryTool extends Tool
{
    public function handle(Request $request, DailySummary $summary): Response
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return Response::json($summary->forRange($data['from'], $data['to']));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('YYYY-MM-DD')->required(),
            'to' => $schema->string()->description('YYYY-MM-DD')->required(),
        ];
    }
}
