<?php

use App\Enums\FoodSource;
use App\Models\Food;

function importFixtureDirectory(): string
{
    $dir = sys_get_temp_dir().'/usda-'.uniqid();
    mkdir($dir);

    file_put_contents("{$dir}/food.csv", <<<'CSV'
"fdc_id","data_type","description","food_category_id","publication_date"
"1","sr_legacy_food","Oats","8","2019-04-01"
"2","branded_food","Some branded cereal","","2019-04-01"
"3","foundation_food","Mystery, no energy","","2019-04-01"
"4","survey_fndds_food","Egg, whole, ""boiled""","","2019-04-01"
CSV);

    file_put_contents("{$dir}/food_nutrient.csv", <<<'CSV'
"id","fdc_id","nutrient_id","amount"
"1","1","1008","389"
"2","1","1003","16.9"
"3","1","1005","66.3"
"4","1","1004","6.9"
"5","1","1079","10.6"
"6","1","1093","2"
"7","1","1114","0"
"8","2","1008","400"
"9","3","1003","5"
"10","4","2047","155"
"11","4","1003","12.6"
CSV);

    return $dir;
}

it('imports USDA foods of the chosen types with micronutrients only where present', function () {
    $this->artisan('usda:import', ['directory' => importFixtureDirectory()])->assertSuccessful();

    $oats = Food::where('name', 'Oats')->sole();

    expect(Food::count())->toBe(2)
        ->and($oats->source)->toBe(FoodSource::Usda)
        ->and($oats->kcal)->toBe(389.0)
        ->and($oats->fiber)->toBe(10.6)
        ->and($oats->sugar)->toBeNull()
        ->and($oats->sodium_mg)->toBe(2.0)
        ->and($oats->vitamin_d_ug)->toBe(0.0)
        ->and($oats->iron_mg)->toBeNull();

    $egg = Food::where('external_id', '4')->sole();
    expect($egg->name)->toBe('Egg, whole, "boiled"')->and($egg->kcal)->toBe(155.0)->and($egg->carbs)->toBe(0.0);
});

it('is idempotent when re-run', function () {
    $dir = importFixtureDirectory();

    $this->artisan('usda:import', ['directory' => $dir])->assertSuccessful();
    $this->artisan('usda:import', ['directory' => $dir])->assertSuccessful();

    expect(Food::count())->toBe(2);
});

it('keeps the user\'s own name of a food when re-run', function () {
    $dir = importFixtureDirectory();

    $this->artisan('usda:import', ['directory' => $dir])->assertSuccessful();
    Food::where('external_id', '4')->update(['display_name' => 'Főtt tojás']);
    $this->artisan('usda:import', ['directory' => $dir])->assertSuccessful();

    expect(Food::where('external_id', '4')->sole()->displayName())->toBe('Főtt tojás');
});

it('reads the Survey export that uses nutrient numbers instead of ids', function () {
    $dir = sys_get_temp_dir().'/usda-'.uniqid();
    mkdir($dir);

    file_put_contents("{$dir}/food.csv", <<<'CSV'
"fdc_id","data_type","description","food_category_id","publication_date"
"9","survey_fndds_food","Milk, NFS","1004","2022-10-28"
CSV);
    file_put_contents("{$dir}/nutrient.csv", <<<'CSV'
"id","name","unit_name","nutrient_nbr","rank"
"1008","Energy","KCAL","208","300.0"
"1003","Protein","G","203","600.0"
"1005","Carbohydrate, by difference","G","205","1110.0"
"1050","Carbohydrate, by summation","G","205.2","1120.0"
CSV);
    file_put_contents("{$dir}/food_nutrient.csv", <<<'CSV'
"id","fdc_id","nutrient_id","amount"
"1","9","208","61"
"2","9","203","3.2"
"3","9","205","4.7"
CSV);

    $this->artisan('usda:import', ['directory' => $dir])->assertSuccessful();

    $milk = Food::where('external_id', '9')->sole();
    expect($milk->kcal)->toBe(61.0)->and($milk->protein)->toBe(3.2)->and($milk->carbs)->toBe(4.7);
});

it('fails on a folder without the CSV files', function () {
    $this->artisan('usda:import', ['directory' => sys_get_temp_dir().'/nope'])->assertFailed();
});

function offDump(): string
{
    $product = fn (string $code, string $name, array $countries, array $nutriments = ['energy-kcal_100g' => 50, 'proteins_100g' => 1, 'carbohydrates_100g' => 5, 'fat_100g' => 2]) => json_encode([
        'code' => $code, 'product_name' => $name, 'countries_tags' => $countries, 'nutriments' => $nutriments,
    ]);

    $path = sys_get_temp_dir().'/off-'.uniqid().'.jsonl.gz';
    file_put_contents($path, gzencode(implode("\n", [
        $product('1', 'Magyar keksz', ['en:hungary']),
        $product('2', 'German biscuit', ['en:germany']),
        $product('3', 'Adat nélkül', ['en:hungary'], ['energy-kcal_100g' => 50]),
        'not json',
    ])."\n"));

    return $path;
}

it('imports only products of the wanted countries with complete macros from a gzipped dump', function () {
    $this->artisan('off:import', ['file' => offDump()])->assertSuccessful();

    expect(Food::pluck('name')->all())->toBe(['Magyar keksz'])
        ->and(Food::first()->source)->toBe(FoodSource::OpenFoodFacts)
        ->and(Food::first()->barcode)->toBe('1');
});

it('can import every country', function () {
    $this->artisan('off:import', ['file' => offDump(), '--countries' => 'all'])->assertSuccessful();

    expect(Food::count())->toBe(2);
});
