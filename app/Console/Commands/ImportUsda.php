<?php

namespace App\Console\Commands;

use App\Enums\FoodSource;
use App\Support\FoodImporter;
use App\Support\Nutrients;
use Generator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use SplFileObject;

#[Signature('usda:import {directory : Folder with the FoodData Central CSV files (food.csv, food_nutrient.csv)} {--types=foundation_food,sr_legacy_food,survey_fndds_food : Comma-separated data_type values to import}')]
#[Description('Import USDA FoodData Central foods (per 100 g) from the downloaded CSV files')]
class ImportUsda extends Command
{
    /** FDC nutrient id => foods column. */
    private const array NUTRIENTS = [
        1008 => 'kcal',
        2047 => 'kcal_atwater_general',
        2048 => 'kcal_atwater_specific',
        1003 => 'protein',
        1005 => 'carbs',
        1004 => 'fat',
        1079 => 'fiber',
        2000 => 'sugar',
        1063 => 'sugar_sr',
        1258 => 'saturated_fat_g',
        1093 => 'sodium_mg',
        1092 => 'potassium_mg',
        1087 => 'calcium_mg',
        1089 => 'iron_mg',
        1090 => 'magnesium_mg',
        1162 => 'vitamin_c_mg',
        1114 => 'vitamin_d_ug',
    ];

    public function handle(): int
    {
        $directory = rtrim($this->argument('directory'), '/');
        $types = array_filter(explode(',', $this->option('types')));

        foreach (['food.csv', 'food_nutrient.csv'] as $file) {
            if (! is_file("{$directory}/{$file}")) {
                $this->components->error("Missing {$directory}/{$file}.");

                return self::FAILURE;
            }
        }

        $names = [];
        foreach ($this->rows("{$directory}/food.csv") as $row) {
            if (in_array($row['data_type'], $types, true)) {
                $names[(int) $row['fdc_id']] = $row['description'];
            }
        }

        $numberToId = $this->nutrientNumbers($directory);

        $values = [];
        foreach ($this->rows("{$directory}/food_nutrient.csv") as $row) {
            $fdcId = (int) $row['fdc_id'];
            $column = self::NUTRIENTS[$numberToId[$row['nutrient_id']] ?? (int) $row['nutrient_id']] ?? null;

            if ($column !== null && isset($names[$fdcId]) && is_numeric($row['amount'])) {
                $values[$fdcId][$column] = (float) $row['amount'];
            }
        }

        $importer = new FoodImporter(FoodSource::Usda);
        $skipped = 0;

        foreach ($names as $fdcId => $name) {
            $nutrients = $values[$fdcId] ?? [];
            $kcal = $nutrients['kcal'] ?? $nutrients['kcal_atwater_general'] ?? $nutrients['kcal_atwater_specific'] ?? null;

            // Without energy the food cannot yield trustworthy totals. USDA omits some zero macros, so those default to 0.
            if ($kcal === null) {
                $skipped++;

                continue;
            }

            $importer->add([
                'external_id' => (string) $fdcId,
                'name' => $name,
                'kcal' => $kcal,
                'protein' => $nutrients['protein'] ?? 0.0,
                'carbs' => $nutrients['carbs'] ?? 0.0,
                'fat' => $nutrients['fat'] ?? 0.0,
                'fiber' => $nutrients['fiber'] ?? null,
                'sugar' => $nutrients['sugar'] ?? $nutrients['sugar_sr'] ?? null,
                ...array_intersect_key($nutrients, array_flip(Nutrients::MICROS)),
            ]);
        }

        $this->components->info("Imported {$importer->flush()} USDA foods, skipped {$skipped} without energy value.");

        return self::SUCCESS;
    }

    /**
     * The Survey (FNDDS) export puts the legacy nutrient number (e.g. 208 for energy) in `food_nutrient.nutrient_id`
     * instead of the nutrient id (1008); this maps such numbers to ids. Numbers that are also ids are left alone.
     *
     * @return array<string, int>
     */
    private function nutrientNumbers(string $directory): array
    {
        $path = "{$directory}/nutrient.csv";

        if (! is_file($path)) {
            return [];
        }

        $ids = [];
        $numbers = [];
        foreach ($this->rows($path) as $row) {
            $ids[(int) $row['id']] = true;
            $numbers[$row['nutrient_nbr']] = (int) $row['id'];
        }

        return array_filter($numbers, fn (string|int $number): bool => ! isset($ids[$number]), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function rows(string $path): Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $header = null;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($header === null) {
                $header = $row;

                continue;
            }

            if (count($row) === count($header)) {
                yield array_combine($header, $row);
            }
        }
    }
}
