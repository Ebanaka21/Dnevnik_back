<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\GradeType;
use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TeacherGradesController extends Controller
{
    public function __construct()
    {
        // Middleware настраивается в маршрутах
    }

    /**
     * Получить список оценок учителя с фильтрами
     */
    public function index(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $query = Grade::with([
                'student:id,name,email,student_number',
                'subject:id,name',
                'gradeType:id,name'
            ])->where('teacher_id', $teacherId);

            // Фильтрация по ученику
            if ($request->has('student_id') && !empty($request->student_id)) {
                $query->where('student_id', $request->student_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по типу оценки
            if ($request->has('grade_type_id') && !empty($request->grade_type_id)) {
                $query->where('grade_type_id', $request->grade_type_id);
            }

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->class_id)) {
                $query->whereHas('student.studentClasses', function($q) use ($request) {
                    $q->where('school_class_id', $request->class_id);
                });
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Фильтрация по значению оценки
            if ($request->has('grade_value') && !empty($request->grade_value)) {
                $query->where('grade_value', $request->grade_value);
            }

            // Фильтрация по ученику (поиск по имени)
            if ($request->has('student_search') && !empty($request->student_search)) {
                $query->whereHas('student', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->student_search . '%');
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $grades = $query->paginate(20);

            // Получаем статистику по фильтрам
            $statistics = [
                'total_grades' => $grades->total(),
                'average_grade' => round($query->clone()->avg('value'), 2),
                'grade_distribution' => [
                    '5' => $query->clone()->where('value', 5)->count(),
                    '4' => $query->clone()->where('value', 4)->count(),
                    '3' => $query->clone()->where('value', 3)->count(),
                    '2' => $query->clone()->where('value', 2)->count()
                ]
            ];

            return response()->json([
                'grades' => $grades->items(),
                'pagination' => [
                    'current_page' => $grades->currentPage(),
                    'last_page' => $grades->lastPage(),
                    'per_page' => $grades->perPage(),
                    'total' => $grades->total()
                ],
                'statistics' => $statistics,
                'filters_applied' => $request->only([
                    'student_id', 'subject_id', 'grade_type_id', 'class_id',
                    'date_from', 'date_to', 'grade_value', 'student_search'
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Error in TeacherGradesController::index', [
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении оценок'], 500);
        }
    }

    /**
     * Добавление новой оценки
     */
    public function store(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
                'subject_id' => 'required|exists:subjects,id',
                'grade_type_id' => 'required|exists:grade_types,id',
                'value' => 'required|integer|min:1|max:5',
                'date' => 'required|date',
                'comment' => 'nullable|string|max:500',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем, что ученик является учеником
            $student = User::findOrFail($request->student_id);
            if ($student->role->name !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что учитель ведет этот предмет
            if (!$user->subjects()->where('subject_id', $request->subject_id)->exists()) {
                return response()->json(['error' => 'Вы не ведете этот предмет'], 400);
            }

            // Проверяем, что ученик изучает этот предмет (находится в классе где ведется предмет)
            $hasSubject = $student->studentClasses()
                ->whereHas('schoolClass.subjects', function($query) use ($request) {
                    $query->where('subject_id', $request->subject_id);
                })->exists();

            if (!$hasSubject) {
                return response()->json(['error' => 'Ученик не изучает этот предмет'], 400);
            }

            // Проверяем, что не дублируем оценку
            $existingGrade = Grade::where('student_id', $request->student_id)
                ->where('subject_id', $request->subject_id)
                ->where('teacher_id', $teacherId)
                ->where('grade_type_id', $request->grade_type_id)
                ->whereDate('date', $request->date)
                ->first();

            if ($existingGrade) {
                return response()->json(['error' => 'Оценка за этот день уже существует'], 400);
            }

            $gradeData = $validator->validated();
            $gradeData['teacher_id'] = $teacherId;

            // Автоматически добавляем school_class_id из класса ученика
            $student = User::findOrFail($request->student_id);
            $studentClass = $student->studentClasses()->first();
            if ($studentClass) {
                $gradeData['school_class_id'] = $studentClass->school_class_id;
            }

            $grade = Grade::create($gradeData);
            $grade->load(['student:id,name,email', 'subject:id,name', 'gradeType:id,name']);

            // Создаем уведомление для ученика и родителей
            $this->createGradeNotification($grade);

            Log::info('Grade created successfully by teacher', [
                'grade_id' => $grade->id,
                'teacher_id' => $teacherId,
                'student_id' => $grade->student_id,
                'subject_id' => $grade->subject_id,
                'value' => $grade->value
            ]);

            return response()->json($grade, 201);

        } catch (\Exception $e) {
            Log::error('Error creating grade', [
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании оценки'], 500);
        }
    }

    /**
     * Редактирование оценки
     */
    public function update(Request $request, $gradeId)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $grade = Grade::where('id', $gradeId)
                ->where('teacher_id', $teacherId)
                ->findOrFail($gradeId);

            $validator = Validator::make($request->all(), [
                'value' => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'description' => 'nullable|string|max:500',
                'date' => 'sometimes|required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $oldGradeValue = $grade->grade_value;
            $grade->update($validator->validated());
            $grade->load(['student:id,name,email', 'subject:id,name', 'gradeType:id,name']);

            Log::info('Grade updated successfully by teacher', [
                'grade_id' => $grade->id,
                'teacher_id' => $teacherId,
                'old_grade_value' => $oldGradeValue,
                'new_grade_value' => $grade->grade_value,
                'updated_fields' => $request->only(['grade_value', 'comment', 'max_points', 'earned_points', 'date'])
            ]);

            return response()->json($grade);

        } catch (\Exception $e) {
            Log::error('Error updating grade', [
                'grade_id' => $gradeId,
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении оценки'], 500);
        }
    }

    /**
     * Удаление оценки
     */
    public function destroy($gradeId, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $grade = Grade::where('id', $gradeId)
                ->where('teacher_id', $teacherId)
                ->findOrFail($gradeId);

            $grade->delete();

            Log::info('Grade deleted successfully by teacher', [
                'grade_id' => $gradeId,
                'teacher_id' => $teacherId
            ]);

            return response()->json(['message' => 'Оценка успешно удалена']);

        } catch (\Exception $e) {
            Log::error('Error deleting grade', [
                'grade_id' => $gradeId,
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении оценки'], 500);
        }
    }

    /**
     * Получить типы оценок
     */
    public function types()
    {
        try {
            $gradeTypes = GradeType::all();

            return response()->json([
                'grade_types' => $gradeTypes
            ]);

        } catch (\Exception $e) {
            Log::error('Error in TeacherGradesController::types', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении типов оценок'], 500);
        }
    }

    /**
     * Получить статистику оценок учителя
     */
    public function statistics(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $query = Grade::where('teacher_id', $teacherId);

            // Общая статистика
            $totalGrades = $query->count();
            $averageGrade = round($query->avg('value'), 2);

            // Статистика по предметам
            $subjectStats = $query->clone()->with('subject:id,name')
                ->get()
                ->groupBy('subject_id')
                ->map(function($group) {
                    $subject = $group->first()->subject;
                    return [
                        'subject' => $subject->only(['id', 'name']),
                        'total_grades' => $group->count(),
                        'average_grade' => round($group->avg('value'), 2),
                        'grade_distribution' => [
                            '5' => $group->where('value', 5)->count(),
                            '4' => $group->where('value', 4)->count(),
                            '3' => $group->where('value', 3)->count(),
                            '2' => $group->where('value', 2)->count()
                        ]
                    ];
                })->values();

            // Статистика по ученикам
            $studentStats = $query->clone()->with('student:id,name,email')
                ->get()
                ->groupBy('student_id')
                ->map(function($group) {
                    $student = $group->first()->student;
                    return [
                        'student' => $student->only(['id', 'name', 'email']),
                        'total_grades' => $group->count(),
                        'average_grade' => round($group->avg('value'), 2)
                    ];
                })->values();

            // Статистика по типам оценок
            $gradeTypeStats = $query->clone()->with('gradeType:id,name')
                ->get()
                ->groupBy('grade_type_id')
                ->map(function($group) {
                    $gradeType = $group->first()->gradeType;
                    return [
                        'grade_type' => $gradeType->only(['id', 'name']),
                        'total_grades' => $group->count(),
                        'average_grade' => round($group->avg('value'), 2)
                    ];
                })->values();

            // Месячная статистика
            $monthlyStats = $query->clone()
                ->selectRaw('
                    DATE_FORMAT(date, "%Y-%m") as month,
                    COUNT(*) as total_grades,
                    AVG(value) as average_grade
                ')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->map(function($item) {
                    $item->average_grade = round($item->average_grade, 2);
                    return $item;
                });

            $statistics = [
                'overview' => [
                    'total_grades' => $totalGrades,
                    'average_grade' => $averageGrade,
                    'grade_distribution' => [
                        '5' => $query->clone()->where('value', 5)->count(),
                        '4' => $query->clone()->where('value', 4)->count(),
                        '3' => $query->clone()->where('value', 3)->count(),
                        '2' => $query->clone()->where('value', 2)->count()
                    ]
                ],
                'by_subjects' => $subjectStats,
                'by_students' => $studentStats,
                'by_grade_types' => $gradeTypeStats,
                'monthly' => $monthlyStats
            ];

            return response()->json($statistics);

        } catch (\Exception $e) {
            Log::error('Error in TeacherGradesController::statistics', [
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении статистики'], 500);
        }
    }

    /**
     * Создать уведомление об оценке
     */
    private function createGradeNotification(Grade $grade)
    {
        try {
            // Уведомление для ученика
            $studentNotification = new \App\Models\Notification([
                'user_id' => $grade->student_id,
                'title' => 'Новая оценка',
                'message' => "Получена оценка {$grade->grade_value} по предмету {$grade->subject->name}",
                'type' => 'grade',
                'related_id' => $grade->id,
                'is_read' => false
            ]);
            $studentNotification->save();

            // Уведомления для родителей
            $parents = $grade->student->parentStudents()->with('parent:id,name')->get();
            foreach ($parents as $parentStudent) {
                $parentNotification = new \App\Models\Notification([
                    'user_id' => $parentStudent->parent->id,
                    'title' => 'Оценка вашего ребенка',
                    'message' => "Ваш ребенок {$grade->student->name} получил оценку {$grade->grade_value} по предмету {$grade->subject->name}",
                    'type' => 'grade_parent',
                    'related_id' => $grade->id,
                    'is_read' => false
                ]);
                $parentNotification->save();
            }

        } catch (\Exception $e) {
            Log::warning('Failed to create grade notification', [
                'grade_id' => $grade->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
