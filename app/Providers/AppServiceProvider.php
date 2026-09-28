<?php

namespace App\Providers;

use App\Contracts\VisionProvider;
use App\Services\Vision\GeminiVisionProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VisionProvider::class, GeminiVisionProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::authorizationView(fn (array $parameters) => view('mcp.authorize', $parameters));
    }
}
