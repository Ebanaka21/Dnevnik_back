<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SimpleJwtMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\VerifyParentAccess;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Регистрация глобальных middleware
        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Регистрация алиасов для middleware
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'simple.jwt' => SimpleJwtMiddleware::class,
            'rate.limit' => RateLimitMiddleware::class,
            'verify.parent.access' => VerifyParentAccess::class,
        ]);

        // Настройка для API маршрутов
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Исключаем CSRF для API маршрутов
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
       // Обработка исключений - возвращаем JSON для API запросов
       $exceptions->render(function (\Exception $e, \Illuminate\Http\Request $request) {
           if ($request->expectsJson()) {
               $response = response()->json([
                   'error' => 'Ошибка сервера',
                   'message' => $e->getMessage(),
                   'code' => 500
               ], 500);

               // Добавляем CORS заголовки для ошибок
               $response->header('Access-Control-Allow-Origin', 'http://localhost:3000');
               $response->header('Access-Control-Allow-Credentials', 'true');

               return $response;
           }
       });
   })->create();
