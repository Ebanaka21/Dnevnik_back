<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use App\Events\GradeCreated;
use App\Events\AttendanceMarked;
use App\Events\HomeworkAssigned;
use App\Listeners\SendGradeNotification;
use App\Listeners\SendAttendanceNotification;
use App\Listeners\SendHomeworkNotification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Карта событий и их слушателей
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        GradeCreated::class => [
            SendGradeNotification::class,
        ],
        AttendanceMarked::class => [
            SendAttendanceNotification::class,
        ],
        HomeworkAssigned::class => [
            SendHomeworkNotification::class,
        ],
    ];

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

        // Регистрация событий и слушателей
        $this->registerEventListeners();
    }

    /**
     * Регистрация событий и их слушателей
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
