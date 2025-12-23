<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

// SPA маршрут - возвращаем React приложение
Route::get('/', function (Request $request) {
    return view('welcome');
});

// Дополнительные маршруты для SPA (фронтенд роутинг) - только не-API маршруты
Route::get('/{any}', function (Request $request) {
    // Исключаем API маршруты
    if (str_starts_with($request->path(), 'api/')) {
        abort(404);
    }
    return view('welcome');
})->where('any', '.*');

