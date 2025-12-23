<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run()
    {
        // Создаем роли если их нет
        $studentRole = Role::firstOrCreate(
            ['name' => 'student'],
            ['permissions' => [
                'grades' => ['read'],
                'attendance' => ['read'],
                'homework' => ['read'],
                'profile' => ['read', 'update'],
                'schedule' => ['read'],
            ]]
        );

        $teacherRole = Role::firstOrCreate(
            ['name' => 'teacher'],
            ['permissions' => [
                'grades' => ['read', 'create', 'update', 'delete'],
                'attendance' => ['read', 'create', 'update', 'delete'],
                'homework' => ['read', 'create', 'update', 'delete'],
                'classes' => ['read'],
                'students' => ['read'],
                'schedule' => ['read', 'create', 'update'],
                'profile' => ['read', 'update'],
            ]]
        );

        $parentRole = Role::firstOrCreate(
            ['name' => 'parent'],
            ['permissions' => [
                'grades' => ['read'],
                'attendance' => ['read'],
                'homework' => ['read'],
                'students' => ['read'],
                'schedule' => ['read'],
                'profile' => ['read', 'update'],
            ]]
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['permissions' => [
                'users' => ['read', 'create', 'update', 'delete'],
                'roles' => ['read', 'create', 'update', 'delete'],
                'subjects' => ['read', 'create', 'update', 'delete'],
                'classes' => ['read', 'create', 'update', 'delete'],
                'grades' => ['read', 'create', 'update', 'delete'],
                'attendance' => ['read', 'create', 'update', 'delete'],
                'homework' => ['read', 'create', 'update', 'delete'],
                'schedule' => ['read', 'create', 'update', 'delete'],
                'notifications' => ['read', 'create', 'update', 'delete'],
                'profile' => ['read', 'update'],
            ]]
        );

        // Создаем тестовых пользователей
        $users = [
            [
                'name' => 'Test Student',
                'email' => 'student@test.com',
                'password' => 'password123',
                'role_id' => $studentRole->id,
                'phone' => '+7 (999) 123-45-67',
            ],
            [
                'name' => 'Test Teacher',
                'email' => 'teacher@test.com',
                'password' => 'password123',
                'role_id' => $teacherRole->id,
                'phone' => '+7 (999) 234-56-78',
            ],
            [
                'name' => 'Test Parent',
                'email' => 'parent@test.com',
                'password' => 'password123',
                'role_id' => $parentRole->id,
                'phone' => '+7 (999) 345-67-89',
            ],
            [
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'password' => 'password123',
                'role_id' => $adminRole->id,
                'phone' => '+7 (999) 456-78-90',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'role_id' => $userData['role_id'],
                    'phone' => $userData['phone'],
                ]
            );
        }

        $this->command->info('Тестовые пользователи созданы успешно!');
        $this->command->info('Доступные учетные данные:');
        $this->command->info('Студент: student@test.com / password123');
        $this->command->info('Учитель: teacher@test.com / password123');
        $this->command->info('Родитель: parent@test.com / password123');
        $this->command->info('Админ: admin@test.com / password123');
    }
}
