<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the single account from MACRODB_USER_EMAIL / MACRODB_USER_PASSWORD.
     */
    public function run(): void
    {
        $password = env('MACRODB_USER_PASSWORD');

        if (blank($password) && app()->isProduction()) {
            throw new RuntimeException('Set MACRODB_USER_PASSWORD before seeding in production.');
        }

        User::updateOrCreate(
            ['email' => env('MACRODB_USER_EMAIL', 'me@example.com')],
            ['name' => 'MacroDB', 'password' => $password ?: 'password'],
        );
    }
}
