<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('api:token {name=rest : Label of the token}')]
#[Description('Issue a personal API token for the single account and print it once')]
class CreateApiToken extends Command
{
    public function handle(): int
    {
        $user = User::query()->first();

        if ($user === null) {
            $this->components->error('There is no user yet. Run the database seeder first.');

            return self::FAILURE;
        }

        $this->line($user->createToken($this->argument('name'))->accessToken);

        return self::SUCCESS;
    }
}
