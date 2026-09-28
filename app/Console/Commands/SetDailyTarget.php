<?php

namespace App\Console\Commands;

use App\Models\DailyTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('targets:set {kcal : Daily kcal} {protein : Grams of protein} {carbs : Grams of carbs} {fat : Grams of fat} {--from= : First day (Y-m-d) the target applies to, default today}')]
#[Description('Set the daily targets valid from a date; an existing row for that date is replaced')]
class SetDailyTarget extends Command
{
    public function handle(): int
    {
        $validFrom = $this->option('from') ?: now('Europe/Budapest')->toDateString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom) || strtotime($validFrom) === false) {
            $this->components->error('--from must be a Y-m-d date.');

            return self::FAILURE;
        }

        DailyTarget::updateOrCreate(['valid_from' => $validFrom], [
            'kcal' => (int) $this->argument('kcal'),
            'protein' => (float) $this->argument('protein'),
            'carbs' => (float) $this->argument('carbs'),
            'fat' => (float) $this->argument('fat'),
        ]);

        $this->components->info("Daily target valid from {$validFrom} saved.");

        return self::SUCCESS;
    }
}
