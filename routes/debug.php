<?php

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/debug/users', function () {
    $users = User::with('role')->get();

    return response()->json([
        'users' => $users->map(function ($user) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role ? $user->role->name : 'NO_ROLE',
                'role_id' => $user->role_id,
            ];
        }),
        'roles' => Role::all(),
        'test_accounts' => [
            'admin' => 'admin@school.ru / admin123',
            'teacher' => 'teacher@example.com / password123',
            'student' => 'student@example.com / password123',
            'parent' => 'parent@example.com / password123'
        ]
    ]);
});

Route::post('/debug/test-login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');

    $user = User::where('email', $email)->with('role')->first();

    if (!$user) {
        return response()->json(['error' => 'Пользователь не найден']);
    }

    if (!password_verify($password, $user->password)) {
        return response()->json(['error' => 'Неверный пароль']);
    }

    return response()->json([
        'success' => true,
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role ? $user->role->name : 'NO_ROLE',
            'role_id' => $user->role_id,
        ]
    ]);
});
