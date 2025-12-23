<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\GradeType;
use App\Models\Subject;
use App\Models\User;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    // Получить список всех оценок с фильтрацией по ролям
    public function index(Request $request)
    {
        try {
            $user = request()->attributes->get('user');
            Log::info('GradeController::index called', [
                'user_id' => $user ? $user->id : null,
                'user_role' => $user ? ($user->role ?? 'unknown') : 'unknown',
                'params' => $request->all(),
                'ip' => $request->ip()
            ]);

            $query = Grade::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'subject:id,name',
                'gradeType:id,name'
            ]);

            // Применяем фильтрацию по ролям
            $query = $this->filterGradesByRole($query, $user, $request);

            // Фильтрация по ученику
            if ($request->has('student_id') && !empty($request->student_id)) {
                $query->where('student_id', $request->student_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по типу оценки
            if ($request->has('grade_type_id') && !empty($request->grade_type_id)) {
                $query->where('grade_type_id', $request->grade_type_id);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->class_id)) {
                $query->whereHas('student.studentClasses', function($q) use ($request) {
                    $q->where('school_class_id', $request->class_id);
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $grades = $query->paginate(20);

            return response()->json([
                'data' => $grades->items(),
                'pagination' => [
                    'current_page' => $grades->currentPage(),
                    'last_page' => $grades->lastPage(),
                    'per_page' => $grades->perPage(),
                    'total' => $grades->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении оценок'], 500);
        }
    }

    // Создать новую оценку
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:users,id',
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
            $studentRole = $student->role ?? 'unknown';
            if ($studentRole !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что учитель является учителем
            $teacher = User::findOrFail($request->teacher_id);
            $teacherRole = $teacher->role ?? 'unknown';
            if ($teacherRole !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            // Проверяем, что учитель ведет этот предмет
            $isTeacherForSubject = \DB::table('teacher_subjects')
                ->where('teacher_id', $request->teacher_id)
                ->where('subject_id', $request->subject_id)
                ->exists();

            if (!$isTeacherForSubject) {
                return response()->json(['error' => 'Учитель не ведет этот предмет'], 400);
            }

            $grade = Grade::create($validator->validated());

            // Загружаем связанные данные
            $grade->load(['student:id,name,email', 'teacher:id,name,email', 'subject:id,name', 'gradeType:id,name']);

            // Создаем уведомление для ученика и родителей
            $this->createGradeNotification($grade);

            Log::info('Grade created successfully', [
                'grade_id' => $grade->id,
                'student_id' => $grade->student_id,
                'subject_id' => $grade->subject_id,
                'value' => $grade->grade_value
            ]);

            return response()->json($grade, 201);

        } catch (\Exception $e) {
            Log::error('Error creating grade', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании оценки'], 500);
        }
    }

    // Получить оценки конкретного ученика
    public function studentGrades($studentId, Request $request)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            $studentRole = $student->role ?? 'unknown';

            if ($studentRole !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            $query = Grade::with([
                'subject:id,name',
                'teacher:id,name,email',
                'gradeType:id,name'
            ])->where('student_id', $studentId);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $grades = $query->get();

            // Преобразуем поля для фронтенда
            $gradesFormatted = $grades->map(function($grade) {
                return [
                    'id' => $grade->id,
                    'student_id' => $grade->student_id,
                    'subject_id' => $grade->subject_id,
                    'grade_type_id' => $grade->grade_type_id,
                    'teacher_id' => $grade->teacher_id,
                    'grade_value' => $grade->value, // Переименуем value в grade_value для фронтенда
                    'value' => $grade->value,
                    'date' => $grade->date,
                    'comment' => $grade->comment,
                    'description' => $grade->description,
                    'is_final' => $grade->is_final,
                    'subject' => $grade->subject,
                    'teacher' => $grade->teacher,
                    'gradeType' => $grade->gradeType,
                    'created_at' => $grade->created_at,
                    'updated_at' => $grade->updated_at,
                ];
            });

            // Вычисляем статистику
            $stats = [
                'total_grades' => $gradesFormatted->count(),
                'average_grade' => round($gradesFormatted->avg('grade_value'), 2),
                'by_subject' => $gradesFormatted->groupBy('subject_id')->map(function($group) {
                    return [
                        'subject' => $group->first()['subject']['name'] ?? 'Unknown',
                        'average_grade' => round($group->avg('grade_value'), 2),
                        'total_grades' => $group->count()
                    ];
                })->values()
            ];

            return response()->json([
                'data' => $gradesFormatted
            ]);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::studentGrades", [
                'student_id' => $studentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении оценок ученика'], 500);
        }
    }

    // Получить оценки по предмету
    public function subjectGrades($subjectId, Request $request)
    {
        try {
            $subject = Subject::findOrFail($subjectId);

            $query = Grade::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'gradeType:id,name'
            ])->where('subject_id', $subjectId);

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->class_id)) {
                $query->whereHas('student.studentClasses', function($q) use ($request) {
                    $q->where('school_class_id', $request->class_id);
                });
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            $grades = $query->orderBy('date', 'desc')->get();

            // Статистика по классу
            $stats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('value'), 2),
                'grade_distribution' => $grades->groupBy('value')->map->count(),
                'by_student' => $grades->groupBy('student_id')->map(function($group) {
                    $student = $group->first()->student;
                    return [
                        'student' => $student->only(['id', 'name', 'email']),
                        'average_grade' => round($group->avg('value'), 2),
                        'total_grades' => $group->count()
                    ];
                })->values()
            ];

            return response()->json([
                'subject' => $subject->only(['id', 'name', 'description']),
                'grades' => $grades,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::subjectGrades", [
                'subject_id' => $subjectId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении оценок по предмету'], 500);
        }
    }

    // Получить детали конкретной оценки
    public function show($id)
    {
        try {
            $grade = Grade::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'subject:id,name,description',
                'gradeType:id,name,description'
            ])->findOrFail($id);

            return response()->json($grade);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::show", [
                'grade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Оценка не найдена'], 404);
        }
    }

    // Обновить оценку
    public function update(Request $request, $id)
    {
        try {
            $grade = Grade::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'value' => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'max_points' => 'nullable|integer|min:1|max:100',
                'earned_points' => 'nullable|integer|min:0',
                'date' => 'sometimes|required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $grade->update($validator->validated());
            $grade->load(['student:id,name,email', 'teacher:id,name,email', 'subject:id,name', 'gradeType:id,name']);

            Log::info('Grade updated successfully', [
                'grade_id' => $grade->id,
                'updated_fields' => $request->only(['value', 'comment', 'max_points', 'earned_points', 'date'])
            ]);

            return response()->json($grade);

        } catch (\Exception $e) {
            Log::error('Error updating grade', [
                'grade_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении оценки'], 500);
        }
    }

    // Удалить оценку
    public function destroy($id)
    {
        try {
            $grade = Grade::findOrFail($id);
            $grade->delete();

            Log::info('Grade deleted successfully', [
                'grade_id' => $id
            ]);

            return response()->json(['message' => 'Оценка успешно удалена']);

        } catch (\Exception $e) {
            Log::error('Error deleting grade', [
                'grade_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении оценки'], 500);
        }
    }

    // Получить оценки класса
    public function byClass($classId, Request $request)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $query = Grade::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'subject:id,name',
                'gradeType:id,name'
            ])->whereHas('student.studentClasses', function($q) use ($classId) {
                $q->where('school_class_id', $classId);
            });

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по типу оценки
            if ($request->has('grade_type_id') && !empty($request->grade_type_id)) {
                $query->where('grade_type_id', $request->grade_type_id);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            $grades = $query->orderBy('date', 'desc')->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'class' => $class->only(['id', 'name', 'academic_year']),
                    'message' => 'Оценки не найдены для данного класса',
                    'statistics' => []
                ]);
            }

            // Статистика по ученикам
            $studentsStats = $grades->groupBy('student_id')->map(function($group) {
                $student = $group->first()->student;
                $total = $group->count();
                $average = round($group->avg('value'), 2);

                return [
                    'student' => $student->only(['id', 'name', 'email']),
                    'total_grades' => $total,
                    'average_grade' => $average,
                    'grade_distribution' => [
                        '5' => $group->where('value', 5)->count(),
                        '4' => $group->where('value', 4)->count(),
                        '3' => $group->where('value', 3)->count(),
                        '2' => $group->where('value', 2)->count()
                    ]
                ];
            })->values();

            // Статистика по предметам
            $subjectsStats = $grades->groupBy('subject_id')->map(function($group) {
                $subject = $group->first()->subject;
                $total = $group->count();
                $average = round($group->avg('value'), 2);

                return [
                    'subject' => $subject->only(['id', 'name']),
                    'total_grades' => $total,
                    'average_grade' => $average,
                    'grade_distribution' => [
                        '5' => $group->where('value', 5)->count(),
                        '4' => $group->where('value', 4)->count(),
                        '3' => $group->where('value', 3)->count(),
                        '2' => $group->where('value', 2)->count()
                    ]
                ];
            })->values();

            // Общая статистика
            $overallStats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('value'), 2),
                'grade_distribution' => [
                    '5' => $grades->where('value', 5)->count(),
                    '4' => $grades->where('value', 4)->count(),
                    '3' => $grades->where('value', 3)->count(),
                    '2' => $grades->where('value', 2)->count()
                ],
                'by_students' => $studentsStats,
                'by_subjects' => $subjectsStats,
                'period' => [
                    'from' => $grades->min('date'),
                    'to' => $grades->max('date')
                ]
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'grades' => $grades,
                'statistics' => $overallStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::byClass", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении оценок класса'], 500);
        }
    }

    // Статистика успеваемости класса
    public function statistics($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            // Получаем все оценки класса
            $grades = Grade::with([
                'student:id,name,email',
                'subject:id,name',
                'gradeType:id,name'
            ])->whereHas('student.studentClasses', function($q) use ($classId) {
                $q->where('school_class_id', $classId);
            })->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'class' => $class->only(['id', 'name', 'academic_year']),
                    'message' => 'Оценки не найдены для данного класса',
                    'statistics' => []
                ]);
            }

            // Общая статистика
            $totalGrades = $grades->count();
            $averageGrade = round($grades->avg('value'), 2);
            $gradeDistribution = [
                '5' => $grades->where('value', 5)->count(),
                '4' => $grades->where('value', 4)->count(),
                '3' => $grades->where('value', 3)->count(),
                '2' => $grades->where('value', 2)->count()
            ];

            // Статистика по ученикам
            $studentsStats = $grades->groupBy('student_id')->map(function($group) {
                $student = $group->first()->student;
                $total = $group->count();
                $average = round($group->avg('value'), 2);

                return [
                    'student' => $student->only(['id', 'name', 'email']),
                    'total_grades' => $total,
                    'average_grade' => $average,
                    'best_subject' => $group->groupBy('subject.name')
                        ->map->avg('value')
                        ->sortDesc()
                        ->keys()
                        ->first(),
                    'worst_subject' => $group->groupBy('subject.name')
                        ->map->avg('value')
                        ->sort()
                        ->keys()
                        ->first(),
                    'grade_distribution' => [
                        '5' => $group->where('value', 5)->count(),
                        '4' => $group->where('value', 4)->count(),
                        '3' => $group->where('value', 3)->count(),
                        '2' => $group->where('value', 2)->count()
                    ]
                ];
            })->values();

            // Статистика по предметам
            $subjectsStats = $grades->groupBy('subject_id')->map(function($group) {
                $subject = $group->first()->subject;
                $total = $group->count();
                $average = round($group->avg('value'), 2);

                return [
                    'subject' => $subject->only(['id', 'name']),
                    'total_grades' => $total,
                    'average_grade' => $average,
                    'grade_distribution' => [
                        '5' => $group->where('value', 5)->count(),
                        '4' => $group->where('value', 4)->count(),
                        '3' => $group->where('value', 3)->count(),
                        '2' => $group->where('value', 2)->count()
                    ],
                    'top_students' => $group->groupBy('student_id')
                        ->map->avg('value')
                        ->sortDesc()
                        ->take(3)
                        ->map(function($avg, $studentId) use ($group) {
                            $student = $group->firstWhere('student_id', $studentId)->student;
                            return [
                                'student' => $student->only(['id', 'name', 'email']),
                                'average_grade' => round($avg, 2)
                            ];
                        })->values()
                ];
            })->values();

            // Статистика по месяцам
            $monthlyStats = $grades->groupBy(function($item) {
                return $item->date->format('Y-m');
            })->map(function($group, $month) {
                $total = $group->count();
                $average = round($group->avg('value'), 2);
                return [
                    'month' => $month,
                    'total_grades' => $total,
                    'average_grade' => $average,
                    'grade_distribution' => [
                        '5' => $group->where('value', 5)->count(),
                        '4' => $group->where('value', 4)->count(),
                        '3' => $group->where('value', 3)->count(),
                        '2' => $group->where('value', 2)->count()
                    ]
                ];
            })->values();

            // Статистика по типам оценок
            $gradeTypesStats = $grades->groupBy('grade_type_id')->map(function($group) {
                $gradeType = $group->first()->gradeType;
                $total = $group->count();
                $average = round($group->avg('value'), 2);

                return [
                    'grade_type' => $gradeType->only(['id', 'name', 'description']),
                    'total_grades' => $total,
                    'average_grade' => $average
                ];
            })->values();

            $statistics = [
                'total_grades' => $totalGrades,
                'average_grade' => $averageGrade,
                'grade_distribution' => $gradeDistribution,
                'by_students' => $studentsStats,
                'by_subjects' => $subjectsStats,
                'by_months' => $monthlyStats,
                'by_grade_types' => $gradeTypesStats,
                'period' => [
                    'from' => $grades->min('date'),
                    'to' => $grades->max('date')
                ],
                'top_performers' => $studentsStats->sortByDesc('average_grade')->take(5)->values(),
                'needs_improvement' => $studentsStats->sortBy('average_grade')->take(5)->values()
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::statistics", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении статистики успеваемости'], 500);
        }
    }

    // Получить оценки ребенка (для родителей)
    public function getChildGrades($studentId, Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            // Проверяем, что пользователь является родителем
            $userRole = $user->role ?? 'unknown';
            if ($userRole !== 'parent') {
                return response()->json(['error' => 'Только родители могут просматривать оценки детей'], 403);
            }

            // Проверяем, что родитель связан с этим учеником
            $hasAccess = $user->parentStudents()->where('student_id', $studentId)->exists();
            if (!$hasAccess) {
                return response()->json(['error' => 'Нет доступа к оценкам этого ученика'], 403);
            }

            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            $studentRole = $student->role ?? 'unknown';
            if ($studentRole !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            $query = Grade::with([
                'subject:id,name',
                'teacher:id,name,email',
                'gradeType:id,name'
            ])->where('student_id', $studentId);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $grades = $query->get();

            // Вычисляем статистику
            $stats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('value'), 2),
                'by_subject' => $grades->groupBy('subject_id')->map(function($group) {
                    return [
                        'subject' => $group->first()->subject->name,
                        'average_grade' => round($group->avg('value'), 2),
                        'total_grades' => $group->count()
                    ];
                })->values()
            ];

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'grades' => $grades,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::getChildGrades", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении оценок ребенка'], 500);
        }
    }

    // Фильтрация оценок по ролям
    private function filterGradesByRole($query, $user, $request)
    {
        $userRole = $user->role ?? 'unknown';

        switch ($userRole) {
            case 'student':
                // Студенты видят только свои оценки
                $query->where('student_id', $user->id);
                break;

            case 'parent':
                // Родители видят оценки только своих детей
                $childIds = $user->parentStudents()->pluck('student_id');
                $query->whereIn('student_id', $childIds);
                break;

            case 'teacher':
                // Учителя видят оценки, которые они выставили
                $query->where('teacher_id', $user->id);
                break;

            case 'admin':
                // Администраторы видят все оценки (без дополнительной фильтрации)
                break;
        }

        return $query;
    }

    // Создать уведомление об оценке
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

    // Получить оценки ученика (алиас для studentGrades)
    public function byStudent($studentId, Request $request)
    {
        return $this->studentGrades($studentId, $request);
    }

    // Получить оценки по предмету (алиас для subjectGrades)
    public function bySubject($subjectId, Request $request)
    {
        return $this->subjectGrades($subjectId, $request);
    }

    // Дополнительные методы для учителя

    // Получить типы оценок
    public function types()
    {
        try {
            $types = GradeType::all();
            return response()->json($types);
        } catch (\Exception $e) {
            Log::error("Error in GradeController::types", [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Ошибка при получении типов оценок'], 500);
        }
    }

    // Статистика для учителя
    public function teacherStatistics(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $userRole = $user->role ?? 'unknown';

            if ($userRole !== 'teacher') {
                return response()->json(['error' => 'Доступ только для учителей'], 403);
            }

            $subjectIds = $user->subjects()->pluck('subject_id');
            $grades = Grade::with([
                'student:id,name,email',
                'subject:id,name',
                'gradeType:id,name'
            ])->whereIn('subject_id', $subjectIds)->get();

            $stats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('value'), 2),
                'grade_distribution' => [
                    '5' => $grades->where('value', 5)->count(),
                    '4' => $grades->where('value', 4)->count(),
                    '3' => $grades->where('value', 3)->count(),
                    '2' => $grades->where('value', 2)->count()
                ],
                'by_subject' => $grades->groupBy('subject_id')->map(function($group) {
                    $subject = $group->first()->subject;
                    return [
                        'subject' => $subject->only(['id', 'name']),
                        'total_grades' => $group->count(),
                        'average_grade' => round($group->avg('value'), 2),
                        'students_count' => $group->groupBy('student_id')->count()
                    ];
                })->values()
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error("Error in GradeController::teacherStatistics", [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Ошибка при получении статистики'], 500);
        }
    }

    // Сохранить оценки по уроку (автоматическое определение класса и предмета)
    public function saveByLesson(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lesson_number' => 'required|integer|min:1|max:8',
                'date' => 'required|date',
                'grade_type_id' => 'required|exists:grade_types,id',
                'grades_data' => 'required|array|min:1',
                'grades_data.*.student_id' => 'required|exists:users,id',
                'grades_data.*.value' => 'required|integer|min:1|max:5',
                'grades_data.*.comment' => 'nullable|string|max:500',
                'grades_data.*.description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Получаем текущего пользователя
            $user = $request->attributes->get('user');
            $userRole = $user->role ?? 'unknown';

            if (!$user || $userRole !== 'teacher') {
                return response()->json(['error' => 'Доступ запрещен. Только для учителей.'], 403);
            }

            $teacherId = $user->id;

            // Определяем день недели из даты (1-7, понедельник=1, воскресенье=7)
            $date = \Carbon\Carbon::parse($request->date);
            $dayOfWeek = $date->dayOfWeekIso;

            // Определяем class_id из первого ученика (предполагаем, что все ученики из одного класса)
            $firstStudentId = $request->grades_data[0]['student_id'];
            $firstStudent = User::findOrFail($firstStudentId);
            $firstStudentRole = $firstStudent->role ?? 'unknown';
            if ($firstStudentRole !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            $studentClass = $firstStudent->studentClasses()->first();
            if (!$studentClass) {
                return response()->json(['error' => 'Ученик не принадлежит ни одному классу'], 400);
            }
            $classId = $studentClass->school_class_id;

            // Проверяем, что все ученики из одного класса
            foreach ($request->grades_data as $data) {
                $student = User::findOrFail($data['student_id']);
                $studentRole = $student->role ?? 'unknown';
                if ($studentRole !== 'student') {
                    return response()->json(['error' => 'Все пользователи должны быть учениками'], 400);
                }
                if (!$student->studentClasses()->where('school_class_id', $classId)->exists()) {
                    return response()->json(['error' => 'Все ученики должны принадлежать одному классу'], 400);
                }
            }

            // Ищем расписание для этого урока
            $schedule = \App\Models\Schedule::where('teacher_id', $teacherId)
                ->where('school_class_id', $classId)
                ->where('lesson_number', $request->lesson_number)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->where(function($query) use ($date) {
                    $query->whereNull('effective_from')
                          ->orWhere('effective_from', '<=', $date->toDateString());
                })
                ->where(function($query) use ($date) {
                    $query->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', $date->toDateString());
                })
                ->first();

            if (!$schedule) {
                return response()->json(['error' => 'Урок не найден в вашем расписании на указанную дату'], 404);
            }

            $subjectId = $schedule->subject_id;

            // Сохраняем оценки
            $created = [];
            $errors = [];

            foreach ($request->grades_data as $data) {
                try {
                    // Проверяем дубликаты (ученик + предмет + дата + тип оценки)
                    $existing = Grade::where('student_id', $data['student_id'])
                        ->where('subject_id', $subjectId)
                        ->where('date', $request->date)
                        ->where('grade_type_id', $request->grade_type_id)
                        ->first();

                    if ($existing) {
                        $student = User::findOrFail($data['student_id']);
                        $errors[] = "Оценка для ученика {$student->name} уже существует";
                        continue;
                    }

                    $gradeData = array_merge($data, [
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherId,
                        'date' => $request->date,
                        'grade_type_id' => $request->grade_type_id
                    ]);

                    $grade = Grade::create($gradeData);
                    $grade->load(['student:id,name,email', 'subject:id,name', 'gradeType:id,name']);
                    $created[] = $grade;

                    // Создаем уведомление об оценке
                    $this->createGradeNotification($grade);

                } catch (\Exception $e) {
                    $errors[] = "Ошибка для ученика ID {$data['student_id']}: " . $e->getMessage();
                }
            }

            Log::info('Grades saved by lesson', [
                'teacher_id' => $teacherId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'lesson_number' => $request->lesson_number,
                'date' => $request->date,
                'grade_type_id' => $request->grade_type_id,
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

            return response()->json([
                'created' => $created,
                'errors' => $errors,
                'created_count' => count($created),
                'errors_count' => count($errors),
                'class_id' => $classId,
                'subject_id' => $subjectId
            ]);

        } catch (\Exception $e) {
            Log::error('Error in GradeController::saveByLesson', [
                'teacher_id' => $user->id ?? null,
                'lesson_number' => $request->lesson_number ?? null,
                'date' => $request->date ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при сохранении оценок по уроку'], 500);
        }
    }

    // Массовое сохранение оценок по уроку (одинаковая оценка для всего класса)
    public function bulkSaveByLesson(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lesson_number' => 'required|integer|min:1|max:8',
                'date' => 'required|date',
                'grade_type_id' => 'required|exists:grade_types,id',
                'bulk_value' => 'required|integer|min:1|max:5',
                'bulk_comment' => 'nullable|string|max:500',
                'bulk_description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Получаем текущего пользователя
            $user = $request->attributes->get('user');
            $userRole = $user->role ?? 'unknown';

            if (!$user || $userRole !== 'teacher') {
                return response()->json(['error' => 'Доступ запрещен. Только для учителей.'], 403);
            }

            $teacherId = $user->id;

            // Определяем день недели из даты (1-7, понедельник=1, воскресенье=7)
            $date = \Carbon\Carbon::parse($request->date);
            $dayOfWeek = $date->dayOfWeekIso;

            // Ищем расписание для этого урока
            $schedule = \App\Models\Schedule::where('teacher_id', $teacherId)
                ->where('lesson_number', $request->lesson_number)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->where(function($query) use ($date) {
                    $query->whereNull('effective_from')
                          ->orWhere('effective_from', '<=', $date->toDateString());
                })
                ->where(function($query) use ($date) {
                    $query->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', $date->toDateString());
                })
                ->first();

            if (!$schedule) {
                return response()->json(['error' => 'Урок не найден в вашем расписании на указанную дату'], 404);
            }

            $classId = $schedule->school_class_id;
            $subjectId = $schedule->subject_id;

            // Получаем всех учеников класса
            $students = User::whereHas('studentClasses', function($query) use ($classId) {
                $query->where('school_class_id', $classId);
            })->where('role', 'student')->get();

            if ($students->isEmpty()) {
                return response()->json(['error' => 'В классе нет учеников'], 400);
            }

            // Сохраняем оценки для всех учеников
            $created = [];
            $errors = [];

            foreach ($students as $student) {
                try {
                    // Проверяем дубликаты
                    $existing = Grade::where('student_id', $student->id)
                        ->where('subject_id', $subjectId)
                        ->where('date', $request->date)
                        ->where('grade_type_id', $request->grade_type_id)
                        ->first();

                    if ($existing) {
                        $errors[] = "Оценка для ученика {$student->name} уже существует";
                        continue;
                    }

                    $gradeData = [
                        'student_id' => $student->id,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherId,
                        'grade_type_id' => $request->grade_type_id,
                        'value' => $request->bulk_value,
                        'date' => $request->date,
                        'comment' => $request->bulk_comment,
                        'description' => $request->bulk_description,
                    ];

                    $grade = Grade::create($gradeData);
                    $grade->load(['student:id,name,email', 'subject:id,name', 'gradeType:id,name']);
                    $created[] = $grade;

                    // Создаем уведомление об оценке
                    $this->createGradeNotification($grade);

                } catch (\Exception $e) {
                    $errors[] = "Ошибка для ученика {$student->name}: " . $e->getMessage();
                }
            }

            Log::info('Bulk grades saved by lesson', [
                'teacher_id' => $teacherId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'lesson_number' => $request->lesson_number,
                'date' => $request->date,
                'grade_type_id' => $request->grade_type_id,
                'bulk_value' => $request->bulk_value,
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

            return response()->json([
                'created' => $created,
                'errors' => $errors,
                'created_count' => count($created),
                'errors_count' => count($errors),
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'bulk_value' => $request->bulk_value
            ]);

        } catch (\Exception $e) {
            Log::error('Error in GradeController::bulkSaveByLesson', [
                'teacher_id' => $user->id ?? null,
                'lesson_number' => $request->lesson_number ?? null,
                'date' => $request->date ?? null,
                'bulk_value' => $request->bulk_value ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при массовом сохранении оценок по уроку'], 500);
        }
    }
}
