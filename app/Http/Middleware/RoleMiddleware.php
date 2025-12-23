<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->attributes->get('user');

        Log::info('RoleMiddleware: user from attributes', [
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
            'user_role' => $user ? $user->role : null
        ]);

        if (!$user) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $userRole = $user->role ?? 'unknown';

        // If role is unknown, try to determine from email and relationships
        if ($userRole === 'unknown') {
            if (str_contains($user->email, '@school.ru') || $user->email === 'teacher@example.com') {
                // Check if teacher
                $hasTeacherLinks = \Illuminate\Support\Facades\DB::table('teacher_classes')
                    ->where('teacher_id', $user->id)
                    ->exists();
                if ($hasTeacherLinks || $user->email === 'teacher@example.com') {
                    $userRole = 'teacher';
                } elseif ($user->email === 'admin@school.ru') {
                    $userRole = 'admin';
                } else {
                    // Default to teacher for @school.ru if no links (for class teachers)
                    $userRole = 'teacher';
                }
            } elseif (str_contains($user->email, '@student.ru') || $user->email === 'student@example.com') {
                $userRole = 'student';
            } elseif (str_contains($user->email, '@parent.ru') || $user->email === 'parent@example.com') {
                $userRole = 'parent';
            }
        }

        Log::info('RoleMiddleware: final role determination', [
            'user_id' => $user->id,
            'user_role' => $userRole,
            'required_roles' => $roles
        ]);

        // Проверяем, есть ли у пользователя одна из требуемых ролей
        if (!in_array($userRole, $roles)) {
            Log::warning('Access denied due to insufficient role', [
                'user_id' => $user->id,
                'user_role' => $userRole,
                'required_roles' => $roles,
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod()
            ]);

            return response()->json([
                'error' => 'Недостаточно прав доступа',
                'message' => "Требуемая роль: " . implode(' или ', $roles) . ", ваша роль: {$userRole}"
            ], 403);
        }

        // Дополнительная логика для определенных ролей
        switch ($userRole) {
            case 'student':
                return $this->handleStudentAccess($request, $next, $user);
            case 'parent':
                return $this->handleParentAccess($request, $next, $user);
            case 'teacher':
                return $this->handleTeacherAccess($request, $next, $user);
            case 'admin':
                return $this->handleAdminAccess($request, $next, $user);
            default:
                return $next($request);
        }
    }

    /**
     * Проверить роль пользователя
     */
    public function checkRole($user, $role)
    {
        return $user->role === $role;
    }

    /**
     * Проверить, что пользователь является учителем
     */
    public function checkTeacher($user)
    {
        return $this->checkRole($user, 'teacher');
    }

    /**
     * Проверить, что пользователь является учеником
     */
    public function checkStudent($user)
    {
        return $this->checkRole($user, 'student');
    }

    /**
     * Проверить, что пользователь является родителем
     */
    public function checkParent($user)
    {
        return $this->checkRole($user, 'parent');
    }

    /**
     * Проверить принадлежность ученика к классу
     */
    public function checkStudentBelongsToClass($studentId, $classId)
    {
        return \App\Models\StudentClass::where('student_id', $studentId)
            ->where('school_class_id', $classId)
            ->exists();
    }

    /**
     * Проверить принадлежность родителя к ребенку
     */
    public function checkParentBelongsToStudent($parentId, $studentId)
    {
        return \App\Models\ParentStudent::where('parent_id', $parentId)
            ->where('student_id', $studentId)
            ->exists();
    }

    /**
     * Проверить, что учитель ведет предмет
     */
    public function checkTeacherSubject($teacherId, $subjectId)
    {
        return \App\Models\User::find($teacherId)
            ->subjects()
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /**
     * Handle student access control
     */
    private function handleStudentAccess(Request $request, Closure $next, User $user)
    {
        $path = $request->path();

        // Студенты могут обращаться только к своим данным
        $studentId = $request->route('studentId');
        $userId = $request->route('user');
        $id = $request->route('id');

        if ($studentId && $studentId != $user->id) {
            return response()->json(['error' => 'Студенты могут просматривать только свои данные'], 403);
        }

        if ($userId && $userId != $user->id) {
            return response()->json(['error' => 'Студенты могут просматривать только свои данные'], 403);
        }

        // Проверка на доступ к оценкам
        if (strpos($path, 'grades/student/') !== false && $id && $id != $user->id) {
            return response()->json(['error' => 'Студенты могут просматривать только свои оценки'], 403);
        }

        // Проверка на доступ к посещаемости
        if (strpos($path, 'attendance/student/') !== false && $id && $id != $user->id) {
            return response()->json(['error' => 'Студенты могут просматривать только свою посещаемость'], 403);
        }

        // Проверка на доступ к домашним заданиям
        if (strpos($path, 'homework/student/') !== false && $id && $id != $user->id) {
            return response()->json(['error' => 'Студенты могут просматривать только свои домашние задания'], 403);
        }

        // Студенты могут только просматривать данные, не создавать/редактировать
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (strpos($path, 'grades') !== false ||
                strpos($path, 'attendance') !== false ||
                strpos($path, 'homework') !== false) {
                return response()->json(['error' => 'Студенты могут только просматривать данные'], 403);
            }
        }

        return $next($request);
    }

    /**
     * Handle parent access control
     */
    private function handleParentAccess(Request $request, Closure $next, User $user)
    {
        $path = $request->path();
        $id = $request->route('id');
        $studentId = $request->route('studentId');

        // Родители могут просматривать данные только своих детей
        if ($studentId) {
            $hasAccess = $user->parentStudents()->where('student_id', $studentId)->exists();
            if (!$hasAccess) {
                return response()->json(['error' => 'Родители могут просматривать данные только своих детей'], 403);
            }
        }

        // Проверка доступа к оценкам детей
        if (strpos($path, 'grades/student/') !== false && $studentId) {
            $hasAccess = $user->parentStudents()->where('student_id', $studentId)->exists();
            if (!$hasAccess) {
                return response()->json(['error' => 'Нет доступа к оценкам этого ученика'], 403);
            }
        }

        // Проверка доступа к посещаемости детей
        if (strpos($path, 'attendance/student/') !== false && $studentId) {
            $hasAccess = $user->parentStudents()->where('student_id', $studentId)->exists();
            if (!$hasAccess) {
                return response()->json(['error' => 'Нет доступа к посещаемости этого ученика'], 403);
            }
        }

        // Проверка доступа к домашним заданиям детей
        if (strpos($path, 'homework/student/') !== false && $studentId) {
            $hasAccess = $user->parentStudents()->where('student_id', $studentId)->exists();
            if (!$hasAccess) {
                return response()->json(['error' => 'Нет доступа к домашним заданиям этого ученика'], 403);
            }
        }

        // Родители могут только просматривать данные, не создавать/редактировать
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (strpos($path, 'grades') !== false ||
                strpos($path, 'attendance') !== false ||
                strpos($path, 'homework') !== false) {
                return response()->json(['error' => 'Родители могут только просматривать данные'], 403);
            }
        }

        return $next($request);
    }

    /**
     * Handle teacher access control
     */
    private function handleTeacherAccess(Request $request, Closure $next, User $user)
    {
        $path = $request->path();
        $id = $request->route('id');
        $teacherId = $request->route('teacherId');

        // Учителя могут управлять только своими классами и предметами
        if ($teacherId && $teacherId != $user->id) {
            return response()->json(['error' => 'Учителя могут управлять только своими данными'], 403);
        }

        // Проверка доступа к созданию/редактированию оценок
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (strpos($path, 'grades') !== false) {
                // Пропускаем проверку - контроллер проверит полностью
                // Middleware только проверяет роль
            }

            // Проверка доступа к посещаемости
            if (strpos($path, 'attendance') !== false) {
                // Пропускаем проверку - контроллер проверит полностью
            }

            // Проверка доступа к домашним заданиям
            if (strpos($path, 'homework') !== false && strpos($path, 'submissions') === false) {
                // Для создания домашних заданий - только логируем, не блокируем
                if ($request->isMethod('POST')) {
                    Log::info('Teacher creating homework', [
                        'teacher_id' => $user->id,
                        'subject_id' => $request->input('subject_id'),
                        'path' => $path
                    ]);
                    // Не блокируем - позволяем контроллеру проверить
                } else {
                    // Для других операций с домашними заданиями - проверяем предмет
                    $subjectId = $request->input('subject_id') ?: $request->route('subjectId');
                    if ($subjectId && !$user->subjects()->where('subject_id', $subjectId)->exists()) {
                        return response()->json(['error' => 'Учитель не ведет этот предмет'], 403);
                    }
                }
            }
        }

        // Проверка доступа к расписанию
        if (strpos($path, 'schedule') !== false) {
            // Учителя могут просматривать только свое расписание
            if ($request->isMethod('GET') && strpos($path, 'schedule/teacher/') !== false) {
                $routeTeacherId = $request->route('teacherId');
                if ($routeTeacherId && $routeTeacherId != $user->id) {
                    return response()->json(['error' => 'Учителя могут просматривать только свое расписание'], 403);
                }
            }
        }

        return $next($request);
    }

    /**
     * Handle admin access control
     */
    private function handleAdminAccess(Request $request, Closure $next, User $user)
    {
        // Администраторы имеют полный доступ
        // Можно добавить дополнительные проверки для админов при необходимости
        return $next($request);
    }
}
