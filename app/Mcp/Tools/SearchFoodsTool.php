<?php

namespace App\Mcp\Tools;

use App\Services\FoodSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search foods and prepared dishes by (partial) Hungarian name, e.g. "zab". Returns ids, names, brands and nutrients per 100 g plus named portions. English USDA foods only match through a Hungarian alias.')]
#[IsReadOnly]
class SearchFoodsTool extends Tool
{
    public function handle(Request $request, FoodSearch $search): Response
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        return Response::json($search->search($data['query'], (int) ($data['limit'] ?? 10))->map->toArray()->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search text, one or more words')->required(),
            'limit' => $schema->integer()->description('Maximum number of results (default 10)'),
        ];
    }
}
