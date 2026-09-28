<?php

namespace App\Mcp\Tools;

use App\Http\Resources\FoodResource;
use App\Services\FoodCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Look up a packaged product by barcode: local database first, then Open Food Facts (the result is saved). Returns an error when nothing is found.')]
#[IsReadOnly(false)]
class GetFoodByBarcodeTool extends Tool
{
    public function handle(Request $request, FoodCatalog $catalog): Response
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:32']]);

        $food = $catalog->findByBarcode($data['barcode']);

        return $food === null
            ? Response::error('Nincs találat.')
            : Response::json((new FoodResource($food))->resolve());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['barcode' => $schema->string()->description('EAN/UPC digits')->required()];
    }
}
