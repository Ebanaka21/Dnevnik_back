<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем роли для всех типов пользователей
        $roles = [
            [
                'name' => 'student',
                'permissions' => [
                    'grades' => ['read'],
                    'attendance' => ['read'],
                    'homework' => ['read'],
                    'profile' => ['read', 'update'],
                    'schedule' => ['read'],
                ],
            ],
            [
                'name' => 'teacher',
                'permissions' => [
                    'grades' => ['read', 'create', 'update', 'delete'],
                    'attendance' => ['read', 'create', 'update', 'delete'],
                    'homework' => ['read', 'create', 'update', 'delete'],
                    'profile' => ['read', 'update'],
                    'schedule' => ['read'],
                    'classes' => ['read'],
                    'analytics' => ['read'],
                    'reports' => ['read'],
                    'students' => ['read', 'update'],
                    'parents' => ['read'],
                ],
            ],
            [
                'name' => 'parent',
                'permissions' => [
                    'grades' => ['read'],
                    'attendance' => ['read'],
                    'homework' => ['read'],
                    'profile' => ['read', 'update'],
                    'schedule' => ['read'],
                    'children' => ['read'],
                    'reports' => ['read'],
                ],
            ],
            [
                'name' => 'admin',
                'permissions' => [
                    'users' => ['read', 'create', 'update', 'delete'],
                    'classes' => ['read', 'create', 'update', 'delete'],
                    'subjects' => ['read', 'create', 'update', 'delete'],
                    'schedule' => ['read', 'create', 'update', 'delete'],
                    'notifications' => ['read', 'create', 'update', 'delete'],
                    'reports' => ['read'],
                    'analytics' => ['read'],
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            Role::create([
                'name' => $roleData['name'],
                'permissions' => $roleData['permissions'],
            ]);
        }
    }
}
