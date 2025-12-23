<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class TwoFactorController extends Controller
{
    /**
     * Включить 2FA для пользователя
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        // Генерируем секретный код
        $secret = $this->generateSecret();

        // Сохраняем секрет во временном кэше на 10 минут
        Cache::put("2fa_secret_{$user->id}", $secret, 600);

        return response()->json([
            'message' => '2FA включен. Проверьте email для подтверждения.',
            'secret' => $secret
        ]);
    }

    /**
     * Подтвердить включение 2FA
     */
    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $secret = Cache::get("2fa_secret_{$user->id}");

        if (!$secret) {
            return response()->json(['error' => 'Секрет истек. Попробуйте снова.'], 400);
        }

        if ($request->code !== $secret) {
            return response()->json(['error' => 'Неверный код подтверждения.'], 400);
        }

        // Включаем 2FA для пользователя
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret
        ]);

        // Очищаем кэш
        Cache::forget("2fa_secret_{$user->id}");

        return response()->json(['message' => '2FA успешно включен.']);
    }

    /**
     * Отключить 2FA
     */
    public function disable(Request $request)
    {
        $user = $request->user();

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null
        ]);

        return response()->json(['message' => '2FA отключен.']);
    }

    /**
     * Отправить код для входа
     */
    public function sendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->two_factor_enabled) {
            return response()->json(['error' => '2FA не включен для этого пользователя.'], 400);
        }

        $code = $this->generateSecret();

        // Сохраняем код в кэше на 5 минут
        Cache::put("2fa_code_{$user->id}", $code, 300);

        // Отправляем код на email
        Mail::raw("Ваш код для входа: {$code}", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Код двухфакторной аутентификации');
        });

        return response()->json(['message' => 'Код отправлен на email.']);
    }

    /**
     * Проверить код 2FA
     */
    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        $cachedCode = Cache::get("2fa_code_{$user->id}");

        if (!$cachedCode || $request->code !== $cachedCode) {
            return response()->json(['error' => 'Неверный или истекший код.'], 400);
        }

        // Очищаем код после успешной проверки
        Cache::forget("2fa_code_{$user->id}");

        // Генерируем временный токен для завершения входа
        $tempToken = bin2hex(random_bytes(32));
        Cache::put("2fa_temp_token_{$tempToken}", $user->id, 300); // 5 минут

        return response()->json([
            'message' => 'Код подтвержден.',
            'temp_token' => $tempToken
        ]);
    }

    /**
     * Завершить вход после 2FA
     */
    public function completeLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Cache::get("2fa_temp_token_{$request->temp_token}");

        if (!$userId) {
            return response()->json(['error' => 'Временный токен истек.'], 400);
        }

        $user = User::find($userId);
        Cache::forget("2fa_temp_token_{$request->temp_token}");

        // Генерируем JWT токен
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    /**
     * Генерировать 6-значный код
     */
    private function generateSecret(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
