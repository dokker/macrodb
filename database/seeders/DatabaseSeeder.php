<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the single account from MACRODB_USER_EMAIL / MACRODB_USER_PASSWORD.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('MACRODB_USER_EMAIL', 'me@example.com')],
            ['name' => 'MacroDB', 'password' => env('MACRODB_USER_PASSWORD', 'password')],
        );
    }
}
