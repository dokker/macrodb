<?php

namespace App\Mcp\Tools;

use App\Services\DailySummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Daily totals, per-meal breakdown with item ids, the daily target and the deviation from it. Days are cut in Europe/Budapest time.')]
#[IsReadOnly]
class GetDailySummaryTool extends Tool
{
    public function handle(Request $request, DailySummary $summary): Response
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return Response::json($summary->forDate($data['date']));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['date' => $schema->string()->description('YYYY-MM-DD')->required()];
    }
}
