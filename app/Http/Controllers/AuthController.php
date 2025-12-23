<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    protected function rateLimit(Request $request, string $keyPrefix = 'auth')
    {
        $key = $keyPrefix . '_' . $request->ip();

        $attempts = (int) env('RATE_LIMIT_ATTEMPTS', 5);
        $decay = (int) env('RATE_LIMIT_DECAY', 60);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            Log::warning('Rate limit exceeded for IP: ' . $request->ip() . ' Key: ' . $key);
            return response()->json(['message' => 'Слишком много попыток. Попробуйте позже.'], 429);
        }

        RateLimiter::hit($key, $decay);
    }

    public function register(Request $request)
    {
        try {
            $this->rateLimit($request, 'register');

            // Оптимизированный парсинг input данных
            $inputData = $request->all();
            if (empty($inputData)) {
                $inputData = json_decode($request->getContent(), true) ?? [];
            }
            if (empty($inputData)) {
                $inputData = $request->post();
            }
            if (empty($inputData)) {
                $inputData = $request->input();
            }

            // Проверяем, что данные получены
            if (empty($inputData)) {
                Log::warning('No input data received for registration from IP: ' . $request->ip());
                return response()->json(['message' => 'Ошибка регистрации', 'errors' => ['general' => 'Нет данных для обработки']], 422);
            }

            $validator = \Illuminate\Support\Facades\Validator::make($inputData, [
                'name' => 'required|string|min:2|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:8|max:255|confirmed',
                'role' => 'nullable|string|in:student,teacher,parent,admin',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for registration: ' . json_encode($validator->errors()) . ' from IP: ' . $request->ip());
                return response()->json(['message' => 'Ошибка валидации', 'errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Санитизация данных
            $name = trim(strip_tags($data['name']));
            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            $phone = isset($data['phone']) ? trim(strip_tags($data['phone'])) : null;

            // Создаем username из name (для совместимости с системой)
            $username = strtolower(str_replace(' ', '_', $name));
            // Ограничиваем длину username до 12 символов
            if (strlen($username) > 12) {
                $username = substr($username, 0, 12);
            }
            // Проверяем уникальность username
            $originalUsername = $username;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $originalUsername . $counter;
                $counter++;
            }

            // Проверка на опасные символы в name
            if (preg_match('/[<>"\'&;]/', $name)) {
                Log::warning('Dangerous characters in registration data from IP: ' . $request->ip());
                return response()->json(['message' => 'Регистрация не удалась', 'errors' => ['general' => 'Недопустимые символы в имени']], 422);
            }

            // Определяем роль пользователя
            $roleName = $data['role'] ?? 'student';

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($data['password']),
                'role' => $roleName,
            ]);

            Log::info('User registered successfully: ' . $user->email . ' (ID: ' . $user->id . ') from IP: ' . $request->ip());

            $tokens = $this->generateTokens($user);

            return response()->json([
                'user' => $user,
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => (int) env('JWT_TTL', 3600),
            ], 201);
        } catch (ValidationException $e) {
            Log::error('Validation error on register: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка регистрации', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Exception on register: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка регистрации', 'error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $this->rateLimit($request, 'login');

            // Оптимизированный парсинг input данных (как в register)
            $inputData = $request->all();
            if (empty($inputData)) {
                $inputData = json_decode($request->getContent(), true) ?? [];
            }
            if (empty($inputData)) {
                $inputData = $request->post();
            }
            if (empty($inputData)) {
                $inputData = $request->input();
            }

            // Проверяем, что данные получены
            if (empty($inputData)) {
                Log::warning('No input data received for login from IP: ' . $request->ip());
                return response()->json(['message' => 'Ошибка входа', 'errors' => ['general' => 'Нет данных для обработки']], 422);
            }

            $validator = \Illuminate\Support\Facades\Validator::make($inputData, [
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8|max:255',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for login: ' . json_encode($validator->errors()) . ' from IP: ' . $request->ip());
                return response()->json(['message' => 'Ошибка валидации', 'errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Улучшенная санитизация входных данных
            $email = filter_var(trim(strip_tags($data['email'])), FILTER_SANITIZE_EMAIL);
            $password = trim($data['password']); // Не санитизируем пароль, чтобы не повредить специальные символы

            $user = User::where('email', $email)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                Log::warning('Invalid credentials for email: ' . $email . ' from IP: ' . $request->ip());
                // Добавляем небольшую задержку для предотвращения brute force
                sleep(1);
                return response()->json(['message' => 'Неверный Email или Пароль'], 401);
            }

            // Проверяем, не заблокирован ли аккаунт (если поле status существует)
            if (isset($user->status) && $user->status === 'blocked') {
                Log::warning('Attempt to login with blocked account: ' . $email . ' from IP: ' . $request->ip());
                return response()->json(['message' => 'Аккаунт заблокирован'], 403);
            }

            Log::info('User logged in successfully: ' . $user->email . ' (ID: ' . $user->id . ')');

            // Очищаем кэш друзей предыдущего пользователя (если был)
            // Это важно при переключении аккаунтов
            $previousUserId = session('previous_user_id');
            if ($previousUserId && $previousUserId != $user->id) {
                Log::debug('Clearing cache for previous user ID: ' . $previousUserId);
                Cache::forget("user:{$previousUserId}:friends");
                Cache::forget("user:{$previousUserId}:all_data");
                Cache::forget("user:{$previousUserId}:incoming_requests");
                Cache::forget("user:{$previousUserId}:outgoing_requests");
            }

            // Сохраняем текущего пользователя в сессии для следующего login
            session(['previous_user_id' => $user->id]);

            // Генерация токена (JWT)
            $tokens = $this->generateTokens($user);

            // TTL на 7 дней (в минутах)
            $ttlMinutes = 60 * 24 * 7;

            // Создаем cookie с токеном
            $cookie = cookie(
                'token',               // имя cookie
                $tokens['token'],      // значение токена
                $ttlMinutes,           // время жизни в минутах
                '/',                   // path
                null,                  // domain
                false,                 // secure
                true                   // HttpOnly
            );

            // Получаем имя роли
            $roleName = $user->role ?? 'student';

            // Подготавливаем данные пользователя для ответа
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleName,
            ];

            return response()->json([
                'user' => $userData,
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $ttlMinutes * 60, // в секундах
            ], 200)->cookie($cookie);

        } catch (ValidationException $e) {
            Log::error('Validation error on login: ' . $e->getMessage());
            return response()->json([
                'message' => 'Неверные данные запроса',
                'errors' => $e->errors()
            ], 422);
        } catch (JWTException $e) {
            Log::error('JWT error on login: ' . $e->getMessage());
            return response()->json(['message' => 'Не удалось создать токен'], 500);
        } catch (\Exception $e) {
            Log::error('Exception on login: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ошибка входа',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me()
    {
        try {
            // Получаем пользователя из request attributes (уже проверен middleware)
            $user = request()->attributes->get('user');

            if (!$user) {
                return response()->json(['message' => 'Пользователь не найден'], 404);
            }

            // Проверяем статус пользователя (если поле status существует)
            if (isset($user->status) && $user->status === 'blocked') {
                Log::warning('Blocked user tried to access /me: ' . $user->email);
                return response()->json(['message' => 'Аккаунт заблокирован'], 403);
            }

            // Получаем имя роли
            $roleName = $user->role ?? 'student';

            // Загружаем дополнительные данные в зависимости от роли
            $responseData = [
                'id' => $user->id,
                'name' => htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8'),
                'surname' => htmlspecialchars($user->surname ?? '', ENT_QUOTES, 'UTF-8'),
                'second_name' => htmlspecialchars($user->second_name ?? '', ENT_QUOTES, 'UTF-8'),
                'birthday' => $user->birthday ? $user->birthday->format('Y-m-d') : null,
                'email' => $user->email, // Email не экранируем, так как он используется в формах
                'phone' => $user->phone,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'role' => $roleName, // Возвращаем имя роли как строку
            ];

            // Для учеников добавляем информацию о классах
            if ($roleName === 'student') {
                $user->load(['studentClassRelationships.schoolClass:id,name,academic_year', 'studentClasses:id,name,academic_year']);
                $responseData['studentClasses'] = $user->studentClassRelationships->map(function ($studentClass) {
                    return [
                        'id' => $studentClass->id,
                        'school_class_id' => $studentClass->school_class_id,
                        'academic_year' => $studentClass->academic_year,
                        'is_active' => $studentClass->is_active,
                        'schoolClass' => $studentClass->schoolClass,
                    ];
                });
            }

            // Для родителей добавляем информацию о детях
            if ($roleName === 'parent') {
                $user->load('parentStudents.student:id,name,email');
                $responseData['children'] = $user->parentStudents->map(function ($parentStudent) {
                    return $parentStudent->student;
                });
            }

            // Для учителей добавляем информацию о классах и предметах
            if ($roleName === 'teacher') {
                $user->load([
                    'teacherClasses',
                    'subjects'
                ]);
                $responseData['schoolClasses'] = $user->teacherClasses;
                $responseData['subjects'] = $user->subjects;
            }

            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            Log::error('Exception on me: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = request()->attributes->get('user');

            if (!$user) {
                return response()->json(['message' => 'Пользователь не найден'], 404);
            }

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'name' => 'sometimes|string|min:2|max:255',
                'surname' => 'sometimes|string|min:2|max:255',
                'second_name' => 'sometimes|string|min:2|max:255',
                'birthday' => 'nullable|date_format:Y-m-d',
                'phone' => 'sometimes|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Ошибка валидации', 'errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            $user->update($data);

            return response()->json([
                'message' => 'Профиль обновлен',
                'user' => $user->fresh(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Exception on updateProfile: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка', 'error' => $e->getMessage()], 500);
        }
    }

    public function logout()
    {
        try {
            $token = JWTAuth::parseToken();
            $user = null;
            if ($token) {
                $user = JWTAuth::authenticate();
                // Добавляем токен в blacklist для предотвращения повторного использования
                JWTAuth::invalidate($token);
            }

            if ($user) {
                Log::info('User logged out: ' . $user->email . ' (ID: ' . $user->id . ')');

                // Очищаем кэш друзей при выходе
                Cache::forget("user:{$user->id}:friends");
                Cache::forget("user:{$user->id}:all_data");
                Cache::forget("user:{$user->id}:incoming_requests");
                Cache::forget("user:{$user->id}:outgoing_requests");
                Cache::forget("user:{$user->id}:blocked");

                Log::debug('Cleared cache for user ID: ' . $user->id . ' on logout');
            }

            return response()->json(['message' => 'Успешный выход'], 200);
        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Токен уже истёк, выход выполнен'], 200);
        } catch (\Exception $e) {
            Log::error('Exception on logout: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка выхода', 'error' => $e->getMessage()], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->bearerToken();
            if (!$refreshToken) {
                Log::warning('Refresh token missing from IP: ' . $request->ip());
                return response()->json(['message' => 'Refresh токен отсутствует'], 401);
            }

            $payload = JWTAuth::setToken($refreshToken)->getPayload();

            if ($payload->get('type') !== 'refresh') {
                Log::warning('Invalid refresh token type from IP: ' . $request->ip());
                return response()->json(['message' => 'Неверный refresh токен'], 401);
            }

            // Проверяем IP адрес для дополнительной безопасности
            $tokenIp = $payload->get('ip');
            if ($tokenIp && $tokenIp !== $request->ip()) {
                Log::warning('IP mismatch in refresh token. Token IP: ' . $tokenIp . ', Request IP: ' . $request->ip());
                return response()->json(['message' => 'Неверный refresh токен'], 401);
            }

            $user = User::find($payload->get('sub'));
            if (!$user) {
                Log::warning('User not found for refresh token, user ID: ' . $payload->get('sub'));
                return response()->json(['message' => 'Пользователь не найден'], 404);
            }

            // Проверяем статус пользователя (если поле status существует)
            if (isset($user->status) && $user->status === 'blocked') {
                Log::warning('Blocked user tried to refresh token: ' . $user->email);
                return response()->json(['message' => 'Аккаунт заблокирован'], 403);
            }

            // Проверяем время жизни refresh токена
            $issuedAt = $payload->get('iat');
            $maxRefreshAge = (int) env('JWT_REFRESH_MAX_AGE', 2592000); // 30 дней по умолчанию
            if (now()->timestamp - $issuedAt > $maxRefreshAge) {
                Log::warning('Refresh token too old for user: ' . $user->email);
                return response()->json(['message' => 'Refresh токен устарел'], 401);
            }

            $tokens = $this->generateTokens($user);

            Log::info('Token refreshed for user: ' . $user->email . ' from IP: ' . $request->ip());

            return response()->json([
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => (int) env('JWT_TTL', 3600),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Exception on refresh: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка refresh', 'error' => $e->getMessage()], 500);
        }
    }

    public function internalValidate(Request $request)
    {
        $token = filter_var($request->header('X-Client-Token'), FILTER_SANITIZE_STRING);

        if (!$token) {
            return response()->json(['message' => 'Токен отсутствует'], 400);
        }

        $cacheKey = 'internal_jwt_' . hash('sha256', $token);

        $result = Cache::remember($cacheKey, 300, function () use ($token) {
            try {
                $user = JWTAuth::setToken($token)->authenticate();
                $payload = JWTAuth::getPayload();

                return [
                    'valid' => true,
                    'user_id' => $user->id,
                    'username' => $user->username ?? $user->email,
                    'email' => $user->email,
                    'exp' => $payload->get('exp'),
                ];
            } catch (TokenExpiredException $e) {
                return ['valid' => false, 'message' => 'Токен устарел'];
            } catch (TokenInvalidException $e) {
                return ['valid' => false, 'message' => 'Токен некорректен'];
            } catch (\Exception $e) {
                Log::error('Exception on internalValidate: ' . $e->getMessage());
                return ['valid' => false, 'message' => 'Ошибка валидации'];
            }
        });

        return response()->json($result);
    }

    protected function generateTokens(User $user)
    {
        // Получаем имя роли
        $roleName = $user->role ?? 'unknown';

        // Генерация access token с дополнительными claims для безопасности
        $accessToken = JWTAuth::claims([
            'sub' => $user->id,
            'iat' => now()->timestamp,
            'type' => 'access',
            'role' => $roleName, // Добавляем роль в токен
            'ip' => request()->ip(), // Привязка к IP для дополнительной безопасности
            'user_agent' => substr(request()->userAgent() ?? '', 0, 100), // Ограничение длины
        ])->fromUser($user);

        // Генерация refresh token
        $refreshToken = JWTAuth::claims([
            'sub' => $user->id,
            'type' => 'refresh',
            'role' => $roleName, // Добавляем роль в refresh токен
            'iat' => now()->timestamp,
            'exp' => now()->addSeconds((int) env('JWT_REFRESH_TTL', 604800))->timestamp,
            'ip' => request()->ip(), // Привязка к IP
        ])->fromUser($user);

        return [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }
}
