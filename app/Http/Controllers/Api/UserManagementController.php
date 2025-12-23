<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\ParentStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    // Получить список всех пользователей
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Фильтрация по роли
            if ($request->has('role') && !empty($request->role)) {
                $query->where('role', $request->role);
            }

            // Поиск по имени или email
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy('name')->paginate(15);

            return response()->json([
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in UserManagementController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении пользователей'], 500);
        }
    }

    // Получить только учеников
    public function students(Request $request)
    {
        try {
            $query = User::where('role', 'student');

            // Поиск по имени или email
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Фильтрация по классу
            if ($request->has('class_id') && !empty($request->class_id)) {
                Log::info('Filtering students by class_id', ['class_id' => $request->class_id]);
                $query->whereExists(function($sub) use ($request) {
                    $sub->selectRaw(1)
                        ->from('student_classes')
                        ->whereRaw('student_classes.student_id = users.id')
                        ->where('student_classes.school_class_id', $request->class_id)
                        ->where('student_classes.academic_year', '2024-2025')
                        ->where('student_classes.is_active', true);
                });
            }

            $students = $query->orderBy('name')->get();

            // Добавляем количество родителей для каждого ученика
            $students->transform(function ($student) {
                $student->parents_count = ParentStudent::where('student_id', $student->id)->count();
                return $student;
            });

            Log::info('Students loaded', ['count' => $students->count()]);

            return response()->json([
                'data' => $students
            ]);

        } catch (\Exception $e) {
            Log::error("Error in UserManagementController::students", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Ошибка при получении учеников',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Получить только учителей
    public function teachers(Request $request)
    {
        try {
            $query = User::where('role', 'teacher');

            // Поиск по имени или email
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $teachers = $query->orderBy('name')->get();

            // Загружаем предметы и классы учителя
            $teachers->load([
                'subjects:id,name,description',
                'schoolClasses:id,name,academic_year'
            ]);

            return response()->json($teachers);

        } catch (\Exception $e) {
            Log::error("Error in UserManagementController::teachers", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении учителей'], 500);
        }
    }

    // Получить только родителей
    public function parents(Request $request)
    {
        try {
            $query = User::where('role', 'parent');

            // Поиск по имени или email
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $parents = $query->orderBy('name')->get();

            // Загружаем детей родителя
            $parents->load('parentStudents.student:id,name,email');

            return response()->json($parents);

        } catch (\Exception $e) {
            Log::error("Error in UserManagementController::parents", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении родителей'], 500);
        }
    }

    // Создать нового пользователя
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:6',
                'role' => 'required|string|in:admin,teacher,student,parent',
                'phone' => 'nullable|string|max:20',
                'birthday' => 'nullable|date',
                'address' => 'nullable|string|max:500',
                'avatar' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();
            $data['password'] = Hash::make($data['password']);

            $user = User::create($data);

            Log::info('User created successfully', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role
            ]);

            return response()->json($user, 201);

        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании пользователя'], 500);
        }
    }

    // Получить детали конкретного пользователя
    public function show($id)
    {
        try {
            $user = User::with([
                'studentClasses.schoolClass:id,name,academic_year',
                'parentStudents.student:id,name,email',
                'schoolClasses:id,name,academic_year',
                'subjects:id,name,description'
            ])->findOrFail($id);

            return response()->json($user);

        } catch (\Exception $e) {
            Log::error("Error in UserManagementController::show", [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Пользователь не найден'], 404);
        }
    }

    // Обновить пользователя
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'birthday' => 'nullable|date',
                'address' => 'nullable|string|max:500',
                'avatar' => 'nullable|string',
                'is_active' => 'boolean',
                'role' => 'sometimes|required|string|in:admin,teacher,student,parent',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Если меняем пароль
            if ($request->has('password') && !empty($request->password)) {
                $data['password'] = Hash::make($request->password);
            } else {
                // Убираем поле password из данных для обновления
                unset($data['password']);
            }

            $user->update($data);

            Log::info('User updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => $request->only(['name', 'email', 'role', 'is_active'])
            ]);

            return response()->json($user);

        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении пользователя'], 500);
        }
    }

    // Удалить пользователя
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Проверяем зависимости
            $dependencies = [
                'grades' => $user->grades()->exists(),
                'attendances' => $user->attendances()->exists(),
                'homeworks' => $user->homeworks()->exists(),
                'lessons' => $user->lessons()->exists(),
                'schedules' => $user->schedules()->exists(),
                'notifications' => $user->notifications()->exists(),
            ];

            $existingDependencies = array_filter($dependencies);

            if (!empty($existingDependencies)) {
                $dependencyNames = array_keys($existingDependencies);
                return response()->json([
                    'error' => 'Нельзя удалить пользователя, у которого есть связанные данные: ' . implode(', ', $dependencyNames)
                ], 400);
            }

            $userName = $user->name;
            $user->delete();

            Log::info('User deleted successfully', [
                'user_id' => $id,
                'user_name' => $userName
            ]);

            return response()->json(['message' => 'Пользователь успешно удален']);

        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении пользователя'], 500);
        }
    }

    // Связать родителя с учеником
    public function linkParentStudent(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            Log::info('Link parent-student request', [
                'request_data' => $request->all(),
                'user_id' => $user->id ?? null,
                'user_role' => $user->role ?? null
            ]);

            $validator = Validator::make($request->all(), [
                'parent_id' => 'required|exists:users,id',
                'student_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', ['errors' => $validator->errors()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $parent = User::findOrFail($request->parent_id);
            $student = User::findOrFail($request->student_id);

            Log::info('Users found', [
                'parent' => ['id' => $parent->id, 'name' => $parent->name, 'role' => $parent->role],
                'student' => ['id' => $student->id, 'name' => $student->name, 'role' => $student->role]
            ]);

            // Проверяем роли
            if ($parent->role !== 'parent') {
                Log::error('Parent role check failed', ['parent_role' => $parent->role]);
                return response()->json(['error' => 'Пользователь не является родителем'], 400);
            }

            if ($student->role !== 'student') {
                Log::error('Student role check failed', ['student_role' => $student->role]);
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем количество уже привязанных родителей
            $parentsCount = ParentStudent::where('student_id', $request->student_id)->count();
            Log::info('Parents count check', ['count' => $parentsCount]);

            // Максимум 3 родителя на одного ребенка
            $maxParents = 3;
            if ($parentsCount >= $maxParents) {
                Log::error('Too many parents linked');
                return response()->json([
                    'error' => "У ребенка уже привязано максимальное количество родителей ({$maxParents})",
                    'type' => 'too_many_parents',
                    'parents_count' => $parentsCount,
                    'max_parents' => $maxParents
                ], 422);
            }

            // Проверяем, что этот родитель не привязан уже
            $existingLink = $parent->parentStudents()->where('student_id', $request->student_id)->exists();
            if ($existingLink) {
                Log::info('Link already exists - returning friendly message');
                return response()->json([
                    'error' => 'Этот ребенок уже привязан к вашему аккаунту',
                    'type' => 'already_linked',
                    'parents_count' => $parentsCount
                ], 200);
            }

            $link = $parent->parentStudents()->create([
                'student_id' => $request->student_id
            ]);

            Log::info('Parent-student link created successfully', [
                'link_id' => $link->id,
                'parent_id' => $request->parent_id,
                'student_id' => $request->student_id
            ]);

            return response()->json(['message' => 'Связь между родителем и учеником создана']);

        } catch (\Exception $e) {
            Log::error('Error linking parent-student', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании связи'], 500);
        }
    }

    // Отвязать родителя от ученика
    public function unlinkParentStudent(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'parent_id' => 'required|exists:users,id',
                'student_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $parent = User::findOrFail($request->parent_id);
            $parent->parentStudents()->where('student_id', $request->student_id)->delete();

            Log::info('Parent-student link removed', [
                'parent_id' => $request->parent_id,
                'student_id' => $request->student_id
            ]);

            return response()->json(['message' => 'Связь между родителем и учеником удалена']);

        } catch (\Exception $e) {
            Log::error('Error unlinking parent-student', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении связи'], 500);
        }
    }

    // Получить детей текущего родителя
    public function getMyChildren(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            if (!$user || !in_array($user->role, ['parent', 'admin'])) {
                return response()->json(['error' => 'Доступ запрещен'], 403);
            }

            $children = $user->parentStudents()->with('student:id,name,email')->get();

            return response()->json([
                'data' => $children
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting my children', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении детей'], 500);
        }
    }

    // Связать ученика с классом
    public function linkStudentClass(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
                'school_class_id' => 'required|exists:school_classes,id',
                'academic_year' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $student = User::findOrFail($request->student_id);
            $class = \App\Models\SchoolClass::findOrFail($request->class_id);

            // Проверяем роли
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что связь не существует
            $existingLink = \App\Models\StudentClass::where('student_id', $request->student_id)
                ->where('school_class_id', $request->class_id)
                ->where('academic_year', $request->academic_year ?? '2024-2025')
                ->first();

            if ($existingLink) {
                return response()->json(['error' => 'Связь уже существует'], 400);
            }

            \App\Models\StudentClass::create([
                'student_id' => $request->student_id,
                'school_class_id' => $request->class_id,
                'academic_year' => $request->academic_year ?? '2024-2025',
                'is_active' => true,
                'enrolled_at' => now(),
            ]);

            Log::info('Student-class link created', [
                'student_id' => $request->student_id,
                'school_class_id' => $request->class_id
            ]);

            return response()->json(['message' => 'Ученик привязан к классу']);

        } catch (\Exception $e) {
            Log::error('Error linking student-class', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при создании связи'], 500);
        }
    }

    // Отвязать ученика от класса
    public function unlinkStudentClass(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
                'school_class_id' => 'required|exists:school_classes,id',
                'academic_year' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $deleted = \App\Models\StudentClass::where('student_id', $request->student_id)
                ->where('school_class_id', $request->class_id)
                ->where('academic_year', $request->academic_year ?? '2024-2025')
                ->delete();

            if ($deleted === 0) {
                return response()->json(['error' => 'Связь не найдена'], 404);
            }

            Log::info('Student-class link removed', [
                'student_id' => $request->student_id,
                'school_class_id' => $request->class_id
            ]);

            return response()->json(['message' => 'Ученик отвязан от класса']);

        } catch (\Exception $e) {
            Log::error('Error unlinking student-class', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении связи'], 500);
        }
    }
}
