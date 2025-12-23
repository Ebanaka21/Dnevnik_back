<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class SimpleJwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            Log::info('Simple JWT Middleware Debug', [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'authorization_header' => $request->header('Authorization'),
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 100)
            ]);

            // Извлекаем токен из заголовка Authorization
            $authHeader = $request->header('Authorization');
            if (!$authHeader) {
                Log::warning('Authorization header missing', [
                    'request_uri' => $request->getRequestUri(),
                    'method' => $request->getMethod()
                ]);
                return response()->json(['message' => 'Отсутствует токен авторизации'], 401);
            }

            // Удаляем "Bearer " если присутствует
            $token = str_replace('Bearer ', '', $authHeader);

            Log::info('Token extracted', ['token_length' => strlen($token), 'token_preview' => substr($token, 0, 20) . '...']);

            // Декодируем JWT токен вручную
            $payload = $this->decodeJwtToken($token);
            if (!$payload) {
                Log::warning('Invalid JWT token', [
                    'token_preview' => substr($token, 0, 20) . '...',
                    'request_uri' => $request->getRequestUri(),
                    'method' => $request->getMethod()
                ]);
                return response()->json(['message' => 'Недействительный токен'], 401);
            }

            // Проверяем срок действия
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Log::warning('JWT token expired', [
                    'token_expiry' => $payload['exp'],
                    'current_time' => time(),
                    'request_uri' => $request->getRequestUri(),
                    'method' => $request->getMethod()
                ]);
                return response()->json(['message' => 'Токен истек'], 401);
            }

            // Находим пользователя
            $userId = $payload['sub'] ?? null;
            if (!$userId) {
                Log::warning('Invalid JWT token: no user ID', [
                    'token_preview' => substr($token, 0, 20) . '...',
                    'request_uri' => $request->getRequestUri(),
                    'method' => $request->getMethod()
                ]);
                return response()->json(['message' => 'Неверный токен'], 401);
            }

            Log::info('Looking up user', ['user_id' => $userId]);

            $user = \App\Models\User::find($userId);
            if (!$user) {
                Log::warning('User not found', [
                    'user_id' => $userId,
                    'request_uri' => $request->getRequestUri(),
                    'method' => $request->getMethod()
                ]);
                return response()->json(['message' => 'Пользователь не найден'], 401);
            }

            // Логируем информацию о роли
            Log::info('User found with role', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'role' => $user->role ?? 'no_role'
            ]);

            // Устанавливаем пользователя для запроса
            $request->attributes->set('user', $user);
            Log::info('JWT Authentication successful', [
                'user_id' => $user->id,
                'user_role' => $user->role ?? 'no_role',
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod()
            ]);

        } catch (\Exception $e) {
            Log::error('JWT Authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod()
            ]);
            return response()->json(['message' => 'Ошибка аутентификации'], 401);
        }

        return $next($request);
    }

    private function decodeJwtToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = $parts[1];
            $payload = str_replace(['-', '_'], ['+', '/'], $payload);
            $payload = base64_decode($payload . str_repeat('=', (4 - strlen($payload) % 4) % 4));

            return json_decode($payload, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
