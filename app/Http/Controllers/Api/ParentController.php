<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\Schedule;
use App\Models\ParentStudent;
use App\Models\HomeworkSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Контроллер для родителей
 *
 * Предоставляет API для доступа родителей к данным их детей:
 * - Список детей
 * - Оценки, посещаемость, домашние задания
 * - Расписание и сводная информация
 * - Настройки уведомлений
 */
class ParentController extends Controller
{
    /**
     * Получить список детей текущего родителя
     *
     * GET /api/parent/students
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudents(Request $request): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка роли
            if (!$parent->isParent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен. Только для родителей.',
                ], 403);
            }

            // Получаем детей с информацией о классе
            $children = $parent->children()
                ->with(['studentClasses' => function($query) {
                    $query->where('is_active', true)
                          ->with('schoolClass:id,name,year,letter,academic_year');
                }])
                ->get()
                ->map(function($student) use ($parent) {
                    // Получаем pivot данные о связи
                    $pivot = ParentStudent::where('parent_id', $parent->id)
                        ->where('student_id', $student->id)
                        ->first();

                    // Получаем активный класс
                    $activeClass = $student->studentClasses->first();

                    // Статистика оценок
                    $gradesStats = Grade::where('student_id', $student->id)
                        ->selectRaw('
                            COUNT(*) as total_grades,
                            AVG(value) as average_grade,
                            SUM(CASE WHEN value = 5 THEN 1 ELSE 0 END) as excellent,
                            SUM(CASE WHEN value = 4 THEN 1 ELSE 0 END) as good,
                            SUM(CASE WHEN value = 3 THEN 1 ELSE 0 END) as satisfactory,
                            SUM(CASE WHEN value = 2 THEN 1 ELSE 0 END) as unsatisfactory
                        ')
                        ->first();

                    return [
                        'id' => $student->id,
                        'name' => $student->full_name,
                        'email' => $student->email,
                        'relationship' => $pivot->relationship ?? null,
                        'is_primary' => $pivot->is_primary ?? false,
                        'linked_at' => $pivot->created_at ?? null,
                        'class' => $activeClass ? $activeClass->schoolClass->full_name : null,
                        'class_id' => $activeClass ? $activeClass->school_class_id : null,
                        'academic_year' => $activeClass ? $activeClass->schoolClass->academic_year : null,
                        'statistics' => [
                            'total_grades' => $gradesStats->total_grades ?? 0,
                            'average_grade' => $gradesStats->average_grade ? round($gradesStats->average_grade, 2) : null,
                            'excellent' => $gradesStats->excellent ?? 0,
                            'good' => $gradesStats->good ?? 0,
                            'satisfactory' => $gradesStats->satisfactory ?? 0,
                            'unsatisfactory' => $gradesStats->unsatisfactory ?? 0,
                        ],
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $children,
                'count' => $children->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getStudents', [
                'parent_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка детей',
            ], 500);
        }
    }

    /**
     * Получить оценки ребенка
     *
     * GET /api/parent/students/{studentId}/grades
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStudentGrades(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            // Валидация параметров фильтрации
            $validator = Validator::make($request->all(), [
                'subject_id' => 'nullable|exists:subjects,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'grade_type_id' => 'nullable|exists:grade_types,id',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Запрос оценок с eager loading
            $query = Grade::where('student_id', $studentId)
                ->with([
                    'subject:id,name',
                    'teacher:id,name,surname,second_name',
                    'gradeType:id,name,weight',
                    'schoolClass:id,name,year,letter',
                ]);

            // Применяем фильтры
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            if ($request->has('grade_type_id') && !empty($request->grade_type_id)) {
                $query->where('grade_type_id', $request->grade_type_id);
            }

            // Сортировка
            $query->orderBy('date', 'desc');

            // Пагинация
            $perPage = $request->get('per_page', 20);
            $grades = $query->paginate($perPage);

            // Статистика
            $allGrades = Grade::where('student_id', $studentId)
                ->when($request->subject_id, fn($q) => $q->where('subject_id', $request->subject_id))
                ->when($request->date_from, fn($q) => $q->whereDate('date', '>=', $request->date_from))
                ->when($request->date_to, fn($q) => $q->whereDate('date', '<=', $request->date_to))
                ->get();

            $statistics = [
                'total_grades' => $allGrades->count(),
                'average_grade' => $allGrades->count() > 0 ? round($allGrades->avg('value'), 2) : null,
                'grades_by_value' => [
                    '5' => $allGrades->where('value', 5)->count(),
                    '4' => $allGrades->where('value', 4)->count(),
                    '3' => $allGrades->where('value', 3)->count(),
                    '2' => $allGrades->where('value', 2)->count(),
                ],
                'subjects_count' => $allGrades->unique('subject_id')->count(),
                'by_subject' => $allGrades->groupBy('subject_id')->map(function($subjectGrades) {
                    $subject = $subjectGrades->first()->subject;
                    return [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'count' => $subjectGrades->count(),
                        'average' => round($subjectGrades->avg('value'), 2),
                    ];
                })->values(),
            ];

            return response()->json([
                'success' => true,
                'data' => $grades->items(),
                'pagination' => [
                    'current_page' => $grades->currentPage(),
                    'last_page' => $grades->lastPage(),
                    'per_page' => $grades->perPage(),
                    'total' => $grades->total(),
                ],
                'statistics' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getStudentGrades', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении оценок ученика',
            ], 500);
        }
    }

    /**
     * Получить посещаемость ребенка
     *
     * GET /api/parent/students/{studentId}/attendance
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStudentAttendance(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            // Валидация параметров
            $validator = Validator::make($request->all(), [
                'subject_id' => 'nullable|exists:subjects,id',
                'status' => 'nullable|in:present,absent,late,excused',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Запрос посещаемости
            $query = Attendance::where('student_id', $studentId)
                ->with([
                    'subject:id,name',
                    'teacher:id,name,surname,second_name',
                ]);

            // Фильтры
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Сортировка
            $query->orderBy('date', 'desc');

            // Пагинация
            $perPage = $request->get('per_page', 20);
            $attendances = $query->paginate($perPage);

            // Статистика
            $allAttendances = Attendance::where('student_id', $studentId)
                ->when($request->subject_id, fn($q) => $q->where('subject_id', $request->subject_id))
                ->when($request->date_from, fn($q) => $q->whereDate('date', '>=', $request->date_from))
                ->when($request->date_to, fn($q) => $q->whereDate('date', '<=', $request->date_to))
                ->get();

            $totalRecords = $allAttendances->count();
            $presentCount = $allAttendances->where('status', 'present')->count();

            $statistics = [
                'total_records' => $totalRecords,
                'present' => $presentCount,
                'absent' => $allAttendances->where('status', 'absent')->count(),
                'late' => $allAttendances->where('status', 'late')->count(),
                'excused' => $allAttendances->where('status', 'excused')->count(),
                'attendance_rate' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0,
                'by_subject' => $allAttendances->groupBy('subject_id')->map(function($subjectAttendances) {
                    $subject = $subjectAttendances->first()->subject;
                    $total = $subjectAttendances->count();
                    $present = $subjectAttendances->where('status', 'present')->count();
                    return [
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $subjectAttendances->where('status', 'absent')->count(),
                        'late' => $subjectAttendances->where('status', 'late')->count(),
                        'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                    ];
                })->values(),
            ];

            return response()->json([
                'success' => true,
                'data' => $attendances->items(),
                'pagination' => [
                    'current_page' => $attendances->currentPage(),
                    'last_page' => $attendances->lastPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total(),
                ],
                'statistics' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getStudentAttendance', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении посещаемости ученика',
            ], 500);
        }
    }

    /**
     * Получить домашние задания ребенка
     *
     * GET /api/parent/students/{studentId}/homework
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStudentHomework(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            // Валидация параметров
            $validator = Validator::make($request->all(), [
                'subject_id' => 'nullable|exists:subjects,id',
                'status' => 'nullable|in:pending,submitted,reviewed,overdue',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Получаем класс ученика
            $student = User::findOrFail($studentId);
            $studentClass = $student->studentClasses()->where('is_active', true)->first();

            if (!$studentClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ученик не принадлежит ни одному классу',
                ], 404);
            }

            // Запрос домашних заданий
            $query = Homework::where('school_class_id', $studentClass->school_class_id)
                ->where('is_active', true)
                ->with([
                    'subject:id,name',
                    'teacher:id,name,surname,second_name',
                    'schoolClass:id,name,year,letter',
                ]);

            // Фильтр по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Сортировка по сроку сдачи
            $query->orderBy('due_date', 'asc');

            // Пагинация
            $perPage = $request->get('per_page', 20);
            $homeworks = $query->paginate($perPage);

            // Добавляем информацию о статусе выполнения
            $homeworksWithStatus = $homeworks->getCollection()->map(function($homework) use ($studentId) {
                $submission = HomeworkSubmission::where('homework_id', $homework->id)
                    ->where('student_id', $studentId)
                    ->first();

                $status = 'pending';
                if ($submission) {
                    $status = $submission->status ?? 'submitted';
                } elseif ($homework->due_date < now()) {
                    $status = 'overdue';
                }

                return [
                    'id' => $homework->id,
                    'title' => $homework->title,
                    'description' => $homework->description,
                    'subject' => $homework->subject,
                    'teacher' => $homework->teacher,
                    'assigned_date' => $homework->assigned_date,
                    'due_date' => $homework->due_date,
                    'max_points' => $homework->max_points,
                    'status' => $status,
                    'submission' => $submission ? [
                        'id' => $submission->id,
                        'submitted_at' => $submission->submitted_at,
                        'grade' => $submission->grade,
                        'feedback' => $submission->feedback,
                    ] : null,
                ];
            });

            // Фильтр по статусу (после получения данных)
            if ($request->has('status') && !empty($request->status)) {
                $homeworksWithStatus = $homeworksWithStatus->filter(function($hw) use ($request) {
                    return $hw['status'] === $request->status;
                })->values();
            }

            // Статистика
            $statistics = [
                'total' => $homeworksWithStatus->count(),
                'pending' => $homeworksWithStatus->where('status', 'pending')->count(),
                'submitted' => $homeworksWithStatus->where('status', 'submitted')->count(),
                'reviewed' => $homeworksWithStatus->where('status', 'reviewed')->count(),
                'overdue' => $homeworksWithStatus->where('status', 'overdue')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $homeworksWithStatus,
                'pagination' => [
                    'current_page' => $homeworks->currentPage(),
                    'last_page' => $homeworks->lastPage(),
                    'per_page' => $homeworks->perPage(),
                    'total' => $homeworks->total(),
                ],
                'statistics' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getStudentHomework', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении домашних заданий ученика',
            ], 500);
        }
    }

    /**
     * Получить расписание ребенка
     *
     * GET /api/parent/students/{studentId}/schedule
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStudentSchedule(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            // Валидация параметров
            $validator = Validator::make($request->all(), [
                'day_of_week' => 'nullable|integer|min:1|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Получаем класс ученика
            $student = User::findOrFail($studentId);
            $studentClass = $student->studentClasses()->where('is_active', true)->first();

            if (!$studentClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ученик не принадлежит ни одному классу',
                ], 404);
            }

            // Запрос расписания
            $query = Schedule::where('school_class_id', $studentClass->school_class_id)
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('effective_from')
                      ->orWhere('effective_from', '<=', now()->toDateString());
                })
                ->where(function($q) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', now()->toDateString());
                })
                ->with([
                    'subject:id,name',
                    'teacher:id,name,surname,second_name',
                    'replacementTeacher:id,name,surname,second_name',
                ]);

            // Фильтр по дню недели
            if ($request->has('day_of_week') && !empty($request->day_of_week)) {
                $query->where('day_of_week', $request->day_of_week);
            }

            // Сортировка
            $query->orderBy('day_of_week', 'asc')
                  ->orderBy('lesson_number', 'asc');

            $schedules = $query->get();

            // Группировка по дням недели
            $scheduleByDay = $schedules->groupBy('day_of_week')->map(function($daySchedules, $dayOfWeek) {
                return [
                    'day_of_week' => $dayOfWeek,
                    'day_name' => $this->getDayName($dayOfWeek),
                    'lessons' => $daySchedules->map(function($schedule) {
                        return [
                            'id' => $schedule->id,
                            'lesson_number' => $schedule->lesson_number,
                            'subject' => $schedule->subject,
                            'teacher' => $schedule->replacementTeacher ?? $schedule->teacher,
                            'start_time' => $schedule->start_time ? $schedule->start_time->format('H:i') : null,
                            'end_time' => $schedule->end_time ? $schedule->end_time->format('H:i') : null,
                            'classroom' => $schedule->classroom,
                        ];
                    })->values(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $scheduleByDay,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'class' => $studentClass->schoolClass->full_name,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getStudentSchedule', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении расписания ученика',
            ], 500);
        }
    }

    /**
     * Получить сводную информацию о ребенке (дашборд)
     *
     * GET /api/parent/students/{studentId}/dashboard
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function getStudentDashboard(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            $student = User::with(['studentClasses' => function($query) {
                $query->where('is_active', true)->with('schoolClass');
            }])->findOrFail($studentId);

            $studentClass = $student->studentClasses->first();

            // Последние оценки (5 штук)
            $recentGrades = Grade::where('student_id', $studentId)
                ->with(['subject:id,name', 'gradeType:id,name'])
                ->orderBy('date', 'desc')
                ->limit(5)
                ->get();

            // Статистика оценок
            $gradesStats = Grade::where('student_id', $studentId)
                ->selectRaw('
                    COUNT(*) as total_grades,
                    AVG(value) as average_grade
                ')
                ->first();

            // Статистика посещаемости
            $attendanceStats = Attendance::where('student_id', $studentId)
                ->selectRaw('
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late
                ')
                ->first();

            $attendanceRate = $attendanceStats->total_records > 0
                ? round(($attendanceStats->present / $attendanceStats->total_records) * 100, 2)
                : 0;

            // Активные домашние задания
            $activeHomework = 0;
            if ($studentClass) {
                $activeHomework = Homework::where('school_class_id', $studentClass->school_class_id)
                    ->where('is_active', true)
                    ->where('due_date', '>=', now())
                    ->whereDoesntHave('submissions', function($query) use ($studentId) {
                        $query->where('student_id', $studentId);
                    })
                    ->count();
            }

            // Ближайшие домашние задания (3 штуки)
            $upcomingHomework = [];
            if ($studentClass) {
                $upcomingHomework = Homework::where('school_class_id', $studentClass->school_class_id)
                    ->where('is_active', true)
                    ->where('due_date', '>=', now())
                    ->with(['subject:id,name'])
                    ->orderBy('due_date', 'asc')
                    ->limit(3)
                    ->get()
                    ->map(function($hw) use ($studentId) {
                        $submission = HomeworkSubmission::where('homework_id', $hw->id)
                            ->where('student_id', $studentId)
                            ->first();

                        return [
                            'id' => $hw->id,
                            'title' => $hw->title,
                            'subject' => $hw->subject->name,
                            'due_date' => $hw->due_date,
                            'is_submitted' => $submission !== null,
                        ];
                    });
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->full_name,
                        'email' => $student->email,
                        'class' => $studentClass ? $studentClass->schoolClass->full_name : null,
                        'class_id' => $studentClass ? $studentClass->school_class_id : null,
                    ],
                    'statistics' => [
                        'average_grade' => $gradesStats->average_grade ? round($gradesStats->average_grade, 2) : null,
                        'total_grades' => $gradesStats->total_grades ?? 0,
                        'attendance_rate' => $attendanceRate,
                        'total_lessons' => $attendanceStats->total_records ?? 0,
                        'active_homework' => $activeHomework,
                    ],
                    'recent_grades' => $recentGrades,
                    'attendance_breakdown' => [
                        'present' => $attendanceStats->present ?? 0,
                        'absent' => $attendanceStats->absent ?? 0,
                        'late' => $attendanceStats->late ?? 0,
                    ],
                    'upcoming_homework' => $upcomingHomework,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getStudentDashboard', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении сводной информации об ученике',
            ], 500);
        }
    }

    /**
     * Получить настройки уведомлений для ребенка
     *
     * GET /api/parent/students/{studentId}/notification-settings
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function getNotificationSettings(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            // Заглушка для настроек уведомлений (будет реализовано позже)
            $settings = [
                'notify_bad_grades' => true,
                'notify_absences' => true,
                'notify_late' => true,
                'notify_homework_assigned' => true,
                'notify_homework_deadline' => false,
                'bad_grade_threshold' => 3,
                'homework_deadline_days' => 1,
            ];

            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Настройки уведомлений (функционал в разработке)',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::getNotificationSettings', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении настроек уведомлений',
            ], 500);
        }
    }

    /**
     * Обновить настройки уведомлений для ребенка
     *
     * PUT /api/parent/students/{studentId}/notification-settings
     *
     * @param Request $request
     * @param int $studentId
     * @return JsonResponse
     */
    public function updateNotificationSettings(Request $request, int $studentId): JsonResponse
    {
        try {
            $parent = $request->user();

            // Проверка доступа
            if (!$this->verifyParentAccess($parent, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет доступа к данным этого ученика',
                ], 403);
            }

            // Валидация
            $validator = Validator::make($request->all(), [
                'notify_bad_grades' => 'nullable|boolean',
                'notify_absences' => 'nullable|boolean',
                'notify_late' => 'nullable|boolean',
                'notify_homework_assigned' => 'nullable|boolean',
                'notify_homework_deadline' => 'nullable|boolean',
                'bad_grade_threshold' => 'nullable|integer|min:1|max:5',
                'homework_deadline_days' => 'nullable|integer|min:1|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Заглушка для обновления настроек (будет реализовано позже)
            $settings = array_merge([
                'notify_bad_grades' => true,
                'notify_absences' => true,
                'notify_late' => true,
                'notify_homework_assigned' => true,
                'notify_homework_deadline' => false,
                'bad_grade_threshold' => 3,
                'homework_deadline_days' => 1,
            ], $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Настройки уведомлений обновлены (функционал в разработке)',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in ParentController::updateNotificationSettings', [
                'parent_id' => $request->user()->id ?? null,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении настроек уведомлений',
            ], 500);
        }
    }

    /**
     * Проверить доступ родителя к данным ученика
     *
     * @param User $parent
     * @param int $studentId
     * @return bool
     */
    protected function verifyParentAccess(User $parent, int $studentId): bool
    {
        if (!$parent->isParent()) {
            return false;
        }

        return $parent->isParentOf($studentId);
    }

    /**
     * Получить название дня недели
     *
     * @param int $dayOfWeek
     * @return string
     */
    protected function getDayName(int $dayOfWeek): string
    {
        $days = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];

        return $days[$dayOfWeek] ?? 'Неизвестный день';
    }
}
