<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set application locale and timezone
        config(['app.locale' => env('APP_LOCALE', 'ru')]);
        config(['app.timezone' => env('APP_TIMEZONE', 'Europe/Volgograd')]);

        // Register custom JWT middleware alias
        Route::aliasMiddleware('simple.jwt', \App\Http\Middleware\SimpleJwtMiddleware::class);
    }
}
