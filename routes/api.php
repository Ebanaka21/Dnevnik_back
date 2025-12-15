<?php

use App\Http\Controllers\Api\BookingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HostelController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Все маршруты здесь имеют префикс /api автоматически
| Защищённые маршруты используют middleware simple.jwt
|
*/

// ==================== ПУБЛИЧНЫЕ МАРШРУТЫ (без авторизации) ====================

// Авторизация
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Комнаты / типы номеров — главная страница и поиск
Route::get('/rooms', [RoomController::class, 'types']);                    // Все типы с самой низкой ценой
Route::get('/rooms/available', [RoomController::class, 'available']);     // Поиск по датам: ?check_in=2025-12-10&check_out=2025-12-15
Route::get('/rooms/{slug}', [RoomController::class, 'show']);             // Детали типа по slug
Route::get('/rooms/id/{id}', [RoomController::class, 'getById']);        // Детали конкретного номера по ID

// ==================== ЗАЩИЩЁННЫЕ МАРШРУТЫ (требуют JWT) ====================

Route::middleware('simple.jwt')->group(function () {

    // Пользователь
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Бронирования пользователя
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/pay', [BookingController::class, 'pay']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});

// ==================== ВНУТРЕННИЕ МАРШРУТЫ (для микросервисов или Filament) ====================

Route::middleware('cliente.token')->group(function () {
    Route::post('/internal/validate', [AuthController::class, 'internalValidate']);
    Route::get('/internal/users', fn() =>
        \App\Models\User::select('id', 'name', 'email', 'created_at')->get()
    );
});

// ==================== ЗДОРОВЬЕ ПРИЛОЖЕНИЯ (опционально) ====================

Route::get('/health', fn() => response()->json(['status' => 'ok', 'time' => now()]));
