<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubjectController extends Controller
{
    /**
     * Получить список предметов
     */
    public function index(Request $request)
    {
        try {
            $query = Subject::query();

            // Фильтрация по активности
            if ($request->has('is_active') && $request->is_active !== null) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Фильтрация по часам в неделю
            if ($request->has('hours_per_week') && !empty($request->hours_per_week)) {
                $query->where('hours_per_week', $request->hours_per_week);
            }

            // Поиск по названию
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('short_name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $subjects = $query->paginate(20);

            return response()->json([
                'data' => $subjects->items(),
                'pagination' => [
                    'current_page' => $subjects->currentPage(),
                    'last_page' => $subjects->lastPage(),
                    'per_page' => $subjects->perPage(),
                    'total' => $subjects->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in SubjectController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении предметов'], 500);
        }
    }

    /**
     * Создать новый предмет
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:subjects,name',
                'short_name' => 'required|string|max:50|unique:subjects,short_name',
                'subject_code' => 'required|string|max:10|unique:subjects,subject_code',
                'description' => 'nullable|string|max:1000',
                'hours_per_week' => 'required|integer|min:1|max:10',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $subjectData = array_merge($validator->validated(), [
                'is_active' => $request->boolean('is_active', true)
            ]);

            $subject = Subject::create($subjectData);

            Log::info('Subject created successfully', [
                'subject_id' => $subject->id,
                'name' => $subject->name,
                'short_name' => $subject->short_name
            ]);

            return response()->json($subject, 201);

        } catch (\Exception $e) {
            Log::error('Error creating subject', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании предмета'], 500);
        }
    }

    /**
     * Получить детали конкретного предмета
     */
    public function show($id)
    {
        try {
            $subject = Subject::findOrFail($id);

            // Загружаем дополнительную статистику
            $stats = [
                'total_grades' => $subject->grades()->count(),
                'total_lessons' => $subject->lessons()->count(),
                'total_homeworks' => $subject->homeworks()->count(),
                'total_attendances' => $subject->attendances()->count(),
                'active_schedules' => $subject->schedules()->where('is_active', true)->count(),
            ];

            return response()->json([
                'subject' => $subject,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in SubjectController::show", [
                'subject_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Предмет не найден'], 404);
        }
    }

    /**
     * Обновить предмет
     */
    public function update(Request $request, $id)
    {
        try {
            $subject = Subject::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:subjects,name,' . $id,
                'short_name' => 'sometimes|required|string|max:50|unique:subjects,short_name,' . $id,
                'description' => 'nullable|string|max:1000',
                'hours_per_week' => 'sometimes|required|integer|min:1|max:10',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $updateData = $validator->validated();
            if (isset($updateData['is_active'])) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $subject->update($updateData);

            Log::info('Subject updated successfully', [
                'subject_id' => $subject->id,
                'updated_fields' => array_keys($updateData)
            ]);

            return response()->json($subject);

        } catch (\Exception $e) {
            Log::error('Error updating subject', [
                'subject_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении предмета'], 500);
        }
    }

    /**
     * Удалить предмет
     */
    public function destroy($id)
    {
        try {
            $subject = Subject::findOrFail($id);

            // Проверяем, есть ли связанные данные
            $hasRelatedData = $subject->grades()->exists() ||
                             $subject->lessons()->exists() ||
                             $subject->homeworks()->exists() ||
                             $subject->schedules()->exists();

            if ($hasRelatedData) {
                return response()->json([
                    'error' => 'Нельзя удалить предмет, так как существуют связанные данные (оценки, уроки, задания или расписание)'
                ], 400);
            }

            $subject->delete();

            Log::info('Subject deleted successfully', [
                'subject_id' => $id
            ]);

            return response()->json(['message' => 'Предмет успешно удален']);

        } catch (\Exception $e) {
            Log::error('Error deleting subject', [
                'subject_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении предмета'], 500);
        }
    }

    /**
     * Получить предметы учителя
     */
    public function byTeacher($teacherId)
    {
        try {
            // Проверяем, что пользователь является учителем
            $teacher = User::findOrFail($teacherId);
            if ($teacher->role->name !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            // Получаем предметы, которые ведет учитель
            // Это зависит от структуры связи между учителями и предметами
            // Предположим, что связь осуществляется через уроки
            $subjectIds = $teacher->lessons()->distinct()->pluck('subject_id');
            $subjects = Subject::whereIn('id', $subjectIds)->get();

            // Добавляем статистику для каждого предмета
            $subjectsWithStats = $subjects->map(function($subject) use ($teacherId) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'short_name' => $subject->short_name,
                    'description' => $subject->description,
                    'hours_per_week' => $subject->hours_per_week,
                    'is_active' => $subject->is_active,
                    'statistics' => [
                        'total_lessons' => $subject->lessons()->where('teacher_id', $teacherId)->count(),
                        'total_grades' => $subject->grades()->where('teacher_id', $teacherId)->count(),
                        'total_homeworks' => $subject->homeworks()->where('teacher_id', $teacherId)->count(),
                    ]
                ];
            });

            return response()->json([
                'teacher' => $teacher->only(['id', 'name', 'email']),
                'subjects' => $subjectsWithStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in SubjectController::byTeacher", [
                'teacher_id' => $teacherId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Учитель не найден или ошибка при получении предметов'], 404);
        }
    }

    /**
     * Получить предметы класса
     */
    public function byClass($classId)
    {
        try {
            $schoolClass = SchoolClass::findOrFail($classId);

            // Получаем предметы для класса через учебные планы
            $subjects = Subject::whereHas('curriculumPlans', function($query) use ($classId) {
                $query->where('school_class_id', $classId);
            })->get();

            // Добавляем информацию об учебном плане для каждого предмета
            $subjectsWithPlans = $subjects->map(function($subject) use ($classId) {
                $curriculumPlan = $subject->curriculumPlans()
                    ->where('school_class_id', $classId)
                    ->first();

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'short_name' => $subject->short_name,
                    'description' => $subject->description,
                    'hours_per_week' => $subject->hours_per_week,
                    'is_active' => $subject->is_active,
                    'curriculum_plan' => $curriculumPlan ? [
                        'id' => $curriculumPlan->id,
                        'academic_year' => $curriculumPlan->academic_year,
                        'hours_per_week' => $curriculumPlan->hours_per_week
                    ] : null,
                    'statistics' => [
                        'total_lessons' => $subject->lessons()->where('class_id', $classId)->count(),
                        'total_grades' => $subject->grades()
                            ->whereHas('student.studentClasses', function($query) use ($classId) {
                                $query->where('school_class_id', $classId);
                            })->count(),
                        'total_homeworks' => $subject->homeworks()->where('class_id', $classId)->count(),
                    ]
                ];
            });

            return response()->json([
                'class' => $schoolClass->only(['id', 'name', 'grade']),
                'subjects' => $subjectsWithPlans
            ]);

        } catch (\Exception $e) {
            Log::error("Error in SubjectController::byClass", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Класс не найден или ошибка при получении предметов'], 404);
        }
    }
}
