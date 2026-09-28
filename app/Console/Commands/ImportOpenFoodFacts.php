<?php

namespace App\Console\Commands;

use App\Enums\FoodSource;
use App\Support\FoodImporter;
use App\Support\OpenFoodFactsProduct;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('off:import {file : Open Food Facts JSON Lines dump, optionally .gz (filter it locally first; the full dump is too large)} {--countries=en:hungary : Comma-separated countries_tags to keep, "all" for no filter}')]
#[Description('Import Open Food Facts products from a JSON Lines dump, keeping only the given countries and products with complete macros')]
class ImportOpenFoodFacts extends Command
{
    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        $countries = array_filter(explode(',', $this->option('countries')));
        $filter = $countries !== ['all'];
        $handle = str_ends_with($file, '.gz') ? gzopen($file, 'rb') : fopen($file, 'rb');
        $read = str_ends_with($file, '.gz') ? gzgets(...) : fgets(...);

        $importer = new FoodImporter(FoodSource::OpenFoodFacts);
        $skipped = 0;

        while (($line = $read($handle)) !== false) {
            $product = json_decode($line, true);

            if (! is_array($product)) {
                continue;
            }

            if ($filter && array_intersect($countries, $product['countries_tags'] ?? []) === []) {
                continue;
            }

            $attributes = OpenFoodFactsProduct::toFoodAttributes($product);

            if ($attributes === null) {
                $skipped++;

                continue;
            }

            $importer->add($attributes);
        }

        $this->components->info("Imported {$importer->flush()} Open Food Facts products, skipped {$skipped} with a missing name or macros.");

        return self::SUCCESS;
    }
}
