<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Schedule;
use App\Events\AttendanceMarked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    // Получить список всех записей посещаемости
    public function index(Request $request)
    {
        try {
            Log::info('=== AttendanceController::index START ===', [
                'all_params' => $request->all(),
                'teacher_id_param' => $request->input('teacher_id'),
                'has_teacher_id' => $request->has('teacher_id'),
            ]);

            $query = Attendance::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'subject:id,name'
            ]);

            Log::info('Before filters - Total attendance records:', ['count' => Attendance::count()]);

            // Фильтрация по ученику
            if ($request->has('student_id') && !empty($request->student_id)) {
                Log::info('Filtering by student_id:', ['student_id' => $request->student_id]);
                $query->where('student_id', $request->student_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                Log::info('Filtering by teacher_id:', ['teacher_id' => $request->teacher_id]);
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                Log::info('Filtering by subject_id:', ['subject_id' => $request->subject_id]);
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                Log::info('Filtering by status:', ['status' => $request->status]);
                $query->where('status', $request->status);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                Log::info('Filtering by date_from:', ['date_from' => $request->date_from]);
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                Log::info('Filtering by date_to:', ['date_to' => $request->date_to]);
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->school_class_id)) {
                Log::info('Filtering by school_class_id:', ['school_class_id' => $request->school_class_id]);
                $query->whereHas('student.studentClasses', function($q) use ($request) {
                    $q->where('school_class_id', $request->school_class_id);
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');
            Log::info('Sorting:', ['sort_by' => $sortBy, 'sort_order' => $sortOrder]);
            $query->orderBy($sortBy, $sortOrder);

            $attendances = $query->paginate(20);

            Log::info('=== AttendanceController::index END ===', [
                'count' => $attendances->count(),
                'total' => $attendances->total(),
                'data_sample' => $attendances->items()
            ]);

            return response()->json([
                'data' => $attendances->items(),
                'pagination' => [
                    'current_page' => $attendances->currentPage(),
                    'last_page' => $attendances->lastPage(),
                    'per_page' => $attendances->perPage(),
                    'total' => $attendances->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении записей посещаемости'], 500);
        }
    }

    // Создать новую запись посещаемости
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:users,id',
                'date' => 'required|date',
                'status' => 'required|in:present,absent,late,excused',
                'reason' => 'nullable|string|max:500',
                'lesson_number' => 'nullable|integer|min:1|max:8',
                'comment' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем, что ученик является учеником
            $student = User::findOrFail($request->student_id);
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что учитель является учителем
            $teacher = User::findOrFail($request->teacher_id);
            if ($teacher->role !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            // Проверяем, что учитель ведет этот предмет
            if (!$teacher->subjects()->where('subject_id', $request->subject_id)->exists()) {
                return response()->json(['error' => 'Учитель не ведет этот предмет'], 400);
            }

            // Проверяем, что не дублируем запись
            $existingAttendance = Attendance::where('student_id', $request->student_id)
                ->where('subject_id', $request->subject_id)
                ->where('date', $request->date)
                ->where('lesson_number', $request->lesson_number)
                ->first();

            if ($existingAttendance) {
                return response()->json(['error' => 'Запись посещаемости уже существует'], 400);
            }

            $attendance = Attendance::create($validator->validated());

            // Загружаем связанные данные
            $attendance->load(['student:id,name,email', 'teacher:id,name,email', 'subject:id,name']);

            // Отправляем событие о создании посещаемости
            event(new AttendanceMarked($attendance));

            // Создаем уведомление для родителей при отсутствии
            if ($attendance->status === 'absent') {
                $this->createAbsenceNotification($attendance);
            }

            Log::info('Attendance record created successfully', [
                'attendance_id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'status' => $attendance->status,
                'date' => $attendance->date
            ]);

            return response()->json($attendance, 201);

        } catch (\Exception $e) {
            Log::error('Error creating attendance record', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании записи посещаемости'], 500);
        }
    }

    // Получить посещаемость конкретного ученика
    public function studentAttendance($studentId, Request $request)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            $query = Attendance::with([
                'subject:id,name',
                'teacher:id,name,email'
            ])->where('student_id', $studentId);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
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

            $attendances = $query->get();

            // Вычисляем статистику
            $stats = [
                'total_records' => $attendances->count(),
                'present' => $attendances->where('status', 'present')->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'excused' => $attendances->where('status', 'excused')->count(),
                'attendance_percentage' => $attendances->count() > 0
                    ? round(($attendances->where('status', 'present')->count() / $attendances->count()) * 100, 2)
                    : 0,
                'by_subject' => $attendances->groupBy('subject_id')->map(function($group) {
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();
                    return [
                        'subject' => $group->first()->subject->name,
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values()
            ];

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'attendance' => $attendances,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::studentAttendance", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении посещаемости ученика'], 500);
        }
    }

    // Получить посещаемость класса
    public function classAttendance($classId, Request $request)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $query = Attendance::with([
                'student:id,name,email',
                'subject:id,name',
                'teacher:id,name,email'
            ])->whereHas('student.studentClasses', function($q) use ($classId) {
                $q->where('school_class_id', $classId);
            });

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            // Фильтрация по датам
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            $attendances = $query->orderBy('date', 'desc')->get();

            // Статистика по классу
            $stats = [
                'total_records' => $attendances->count(),
                'present' => $attendances->where('status', 'present')->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'excused' => $attendances->where('status', 'excused')->count(),
                'by_student' => $attendances->groupBy('student_id')->map(function($group) {
                    $student = $group->first()->student;
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();
                    return [
                        'student' => $student->only(['id', 'name', 'email']),
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values()
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'attendance' => $attendances,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::classAttendance", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении посещаемости класса'], 500);
        }
    }

    // Получить детали конкретной записи посещаемости
    public function show($id)
    {
        try {
            $attendance = Attendance::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'subject:id,name'
            ])->findOrFail($id);

            return response()->json($attendance);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::show", [
                'attendance_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Запись посещаемости не найдена'], 404);
        }
    }

    // Обновить запись посещаемости
    public function update(Request $request, $id)
    {
        try {
            $attendance = Attendance::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|required|in:present,absent,late,excused',
                'reason' => 'nullable|string|max:500',
                'lesson_number' => 'nullable|integer|min:1|max:8',
                'comment' => 'nullable|string|max:500',
                'date' => 'sometimes|required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $oldStatus = $attendance->status;
            $attendance->update($validator->validated());
            $attendance->load(['student:id,name,email', 'teacher:id,name,email', 'subject:id,name']);

            // Если статус изменился на отсутствие, создаем уведомление
            if ($oldStatus !== 'absent' && $attendance->status === 'absent') {
                $this->createAbsenceNotification($attendance);
            }

            Log::info('Attendance record updated successfully', [
                'attendance_id' => $attendance->id,
                'old_status' => $oldStatus,
                'new_status' => $attendance->status,
                'updated_fields' => $request->only(['status', 'reason', 'lesson_number', 'comment', 'date'])
            ]);

            return response()->json($attendance);

        } catch (\Exception $e) {
            Log::error('Error updating attendance record', [
                'attendance_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении записи посещаемости'], 500);
        }
    }

    // Удалить запись посещаемости
    public function destroy($id)
    {
        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();

            Log::info('Attendance record deleted successfully', [
                'attendance_id' => $id
            ]);

            return response()->json(['message' => 'Запись посещаемости успешно удалена']);

        } catch (\Exception $e) {
            Log::error('Error deleting attendance record', [
                'attendance_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении записи посещаемости'], 500);
        }
    }

    // Создать уведомление об отсутствии
    private function createAbsenceNotification(Attendance $attendance)
    {
        try {
            // Уведомления для родителей
            $parents = $attendance->student->parentStudents()->with('parent:id,name')->get();
            foreach ($parents as $parentStudent) {
                $parentNotification = new \App\Models\Notification([
                    'user_id' => $parentStudent->parent->id,
                    'title' => 'Ребенок отсутствовал в школе',
                    'message' => "Ваш ребенок {$attendance->student->name} отсутствовал на уроке {$attendance->subject->name} {$attendance->date}",
                    'type' => 'absence',
                    'related_id' => $attendance->id,
                    'is_read' => false
                ]);
                $parentNotification->save();
            }

        } catch (\Exception $e) {
            Log::warning('Failed to create absence notification', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Массовое создание записей посещаемости для класса
    public function bulkCreate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_class_id' => 'required|exists:school_classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:users,id',
                'date' => 'required|date',
                'lesson_number' => 'nullable|integer|min:1|max:8',
                'attendance_data' => 'required|array',
                'attendance_data.*.student_id' => 'required|exists:users,id',
                'attendance_data.*.status' => 'required|in:present,absent,late,excused',
                'attendance_data.*.reason' => 'nullable|string|max:500',
                'attendance_data.*.comment' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем, что учитель является учителем
            $teacher = User::findOrFail($request->teacher_id);
            if ($teacher->role !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            $created = [];
            $errors = [];

            foreach ($request->attendance_data as $data) {
                try {
                    // Проверяем, что ученик в этом классе
                     $student = User::findOrFail($data['student_id']);
                     if (!$student->studentClasses()->where('school_class_id', $request->school_class_id)->exists()) {
                         $errors[] = "Ученик {$student->name} не принадлежит классу";
                         continue;
                     }

                    // Проверяем дубликаты
                    $existing = Attendance::where('student_id', $data['student_id'])
                        ->where('subject_id', $request->subject_id)
                        ->where('date', $request->date)
                        ->where('lesson_number', $request->lesson_number)
                        ->first();

                    if ($existing) {
                        $errors[] = "Запись для ученика {$student->name} уже существует";
                        continue;
                    }

                    $attendanceData = array_merge($data, [
                        'subject_id' => $request->subject_id,
                        'teacher_id' => $request->teacher_id,
                        'date' => $request->date,
                        'lesson_number' => $request->lesson_number
                    ]);

                    $attendance = Attendance::create($attendanceData);
                    $attendance->load(['student:id,name,email', 'subject:id,name']);
                    $created[] = $attendance;

                    // Отправляем событие о создании посещаемости
                    event(new AttendanceMarked($attendance));

                    // Создаем уведомления при отсутствии
                    if ($attendance->status === 'absent') {
                        $this->createAbsenceNotification($attendance);
                    }

                } catch (\Exception $e) {
                    $errors[] = "Ошибка для ученика ID {$data['student_id']}: " . $e->getMessage();
                }
            }

            Log::info('Bulk attendance records created', [
                'school_class_id' => $request->class_id,
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

            return response()->json([
                'created' => $created,
                'errors' => $errors,
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            Log::error('Error in bulk attendance creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при массовом создании записей посещаемости'], 500);
        }
    }

    // Получить посещаемость по дате
    public function byDate($date)
    {
        try {
            $attendances = Attendance::with([
                'student:id,name,email',
                'teacher:id,name,email',
                'subject:id,name'
            ])->whereDate('date', $date)
              ->orderBy('lesson_number', 'asc')
              ->get();

            // Статистика по дате
            $stats = [
                'date' => $date,
                'total_records' => $attendances->count(),
                'present' => $attendances->where('status', 'present')->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'excused' => $attendances->where('status', 'excused')->count(),
                'by_subject' => $attendances->groupBy('subject_id')->map(function($group) {
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();
                    return [
                        'subject' => $group->first()->subject->name,
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values(),
                'by_class' => $attendances->groupBy(function($item) {
                    return $item->student->studentClasses->first()?->school_class_id;
                })->map(function($group) {
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();
                    return [
                        'school_class_id' => $group->first()->student->studentClasses->first()?->school_class_id,
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values()
            ];

            return response()->json([
                'date' => $date,
                'attendance' => $attendances,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::byDate", [
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении посещаемости по дате'], 500);
        }
    }

    // Статистика посещаемости класса
    public function statistics($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            // Получаем все записи посещаемости класса
            $attendances = Attendance::with([
                'student:id,name,email',
                'subject:id,name'
            ])->whereHas('student.studentClasses', function($q) use ($classId) {
                $q->where('school_class_id', $classId);
            })->get();

            if ($attendances->isEmpty()) {
                return response()->json([
                    'class' => $class->only(['id', 'name', 'academic_year']),
                    'message' => 'Записи посещаемости не найдены для данного класса',
                    'statistics' => []
                ]);
            }

            // Общая статистика
            $totalRecords = $attendances->count();
            $presentCount = $attendances->where('status', 'present')->count();
            $absentCount = $attendances->where('status', 'absent')->count();
            $lateCount = $attendances->where('status', 'late')->count();
            $excusedCount = $attendances->where('status', 'excused')->count();

            // Статистика по ученикам
            $studentsStats = $attendances->groupBy('student_id')->map(function($group) {
                $student = $group->first()->student;
                $total = $group->count();
                $present = $group->where('status', 'present')->count();
                $absent = $group->where('status', 'absent')->count();
                $late = $group->where('status', 'late')->count();

                return [
                    'student' => $student->only(['id', 'name', 'email']),
                    'total_lessons' => $total,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $group->where('status', 'excused')->count(),
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            })->values();

            // Статистика по предметам
            $subjectsStats = $attendances->groupBy('subject_id')->map(function($group) {
                $subject = $group->first()->subject;
                $total = $group->count();
                $present = $group->where('status', 'present')->count();
                $absent = $group->where('status', 'absent')->count();
                $late = $group->where('status', 'late')->count();

                return [
                    'subject' => $subject->only(['id', 'name']),
                    'total_lessons' => $total,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $group->where('status', 'excused')->count(),
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            })->values();

            // Статистика по месяцам
            $monthlyStats = $attendances->groupBy(function($item) {
                return $item->date->format('Y-m');
            })->map(function($group, $month) {
                $total = $group->count();
                $present = $group->where('status', 'present')->count();
                return [
                    'month' => $month,
                    'total_lessons' => $total,
                    'present' => $present,
                    'absent' => $group->where('status', 'absent')->count(),
                    'late' => $group->where('status', 'late')->count(),
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            })->values();

            $statistics = [
                'total_records' => $totalRecords,
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'excused' => $excusedCount,
                'overall_attendance_percentage' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0,
                'by_students' => $studentsStats,
                'by_subjects' => $subjectsStats,
                'monthly' => $monthlyStats,
                'period' => [
                    'from' => $attendances->min('date'),
                    'to' => $attendances->max('date')
                ]
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::statistics", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении статистики посещаемости класса'], 500);
        }
    }

    // Получить посещаемость ребенка для родителя
    public function getChildAttendance($studentId, Request $request)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что текущий пользователь является родителем этого ученика
            $user = $request->attributes->get('user');
            if (!$student->parentStudents()->where('parent_id', $user->id)->exists()) {
                return response()->json(['error' => 'Вы не являетесь родителем этого ученика'], 403);
            }

            $query = Attendance::with([
                'subject:id,name',
                'teacher:id,name,email'
            ])->where('student_id', $studentId);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
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

            $attendances = $query->get();

            // Вычисляем статистику
            $stats = [
                'total_records' => $attendances->count(),
                'present' => $attendances->where('status', 'present')->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'excused' => $attendances->where('status', 'excused')->count(),
                'attendance_percentage' => $attendances->count() > 0
                    ? round(($attendances->where('status', 'present')->count() / $attendances->count()) * 100, 2)
                    : 0,
                'by_subject' => $attendances->groupBy('subject_id')->map(function($group) {
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();
                    return [
                        'subject' => $group->first()->subject->name,
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values()
            ];

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'attendance' => $attendances,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in AttendanceController::getChildAttendance", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении посещаемости ребенка'], 500);
        }
    }

    // Получить посещаемость класса на дату (для учителей)
    public function classAttendanceByDate($classId, $date, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            // Проверяем, что учитель преподает в этом классе
            $classExists = SchoolClass::whereHas('teachers', function($query) use ($teacherId) {
                $query->where('user_id', $teacherId);
            })->where('id', $classId)->exists();

            if (!$classExists) {
                return response()->json(['error' => 'У вас нет доступа к этому классу'], 403);
            }

            $class = SchoolClass::findOrFail($classId);

            $attendance = Attendance::with([
                'student:id,name,email,student_number',
                'subject:id,name'
            ])
            ->where('date', $date)
            ->whereHas('student.studentClasses', function($query) use ($classId) {
                $query->where('school_class_id', $classId);
            })
            ->whereHas('subject', function($query) use ($teacherId) {
                $query->whereHas('teachers', function($teacherQuery) use ($teacherId) {
                    $teacherQuery->where('user_id', $teacherId);
                });
            })
            ->orderBy('lesson_number')
            ->get();

            // Получаем всех учеников класса
            $students = User::whereHas('studentClasses', function($query) use ($classId) {
                $query->where('school_class_id', $classId);
            })->select('id', 'name', 'email', 'student_number')->get();

            // Группируем записи по ученикам
            $attendanceByStudent = $attendance->groupBy('student_id');

            // Формируем полный список с отметками
            $studentAttendance = [];
            foreach ($students as $student) {
                $studentAttendance[] = [
                    'student' => $student->toArray(),
                    'attendance_records' => $attendanceByStudent->get($student->id, collect())->toArray(),
                    'has_attendance' => $attendanceByStudent->has($student->id)
                ];
            }

            // Статистика по дате
            $statistics = [
                'date' => $date,
                'total_students' => $students->count(),
                'total_attendance_records' => $attendance->count(),
                'present' => $attendance->where('status', 'present')->count(),
                'absent' => $attendance->where('status', 'absent')->count(),
                'late' => $attendance->where('status', 'late')->count(),
                'excused' => $attendance->where('status', 'excused')->count(),
                'attendance_percentage' => $attendance->count() > 0
                    ? round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 2)
                    : 0
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'date' => $date,
                'student_attendance' => $studentAttendance,
                'attendance_records' => $attendance,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error in AttendanceController::classAttendanceByDate', [
                'school_class_id' => $classId,
                'date' => $date,
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении посещаемости класса'], 500);
        }
    }

    // Получить статистику посещаемости учителя
    public function teacherStatistics(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $query = Attendance::where('teacher_id', $teacherId);

            // Общая статистика
            $totalRecords = $query->count();
            $presentCount = $query->where('status', 'present')->count();
            $absentCount = $query->where('status', 'absent')->count();
            $lateCount = $query->where('status', 'late')->count();
            $excusedCount = $query->where('status', 'excused')->count();

            // Статистика по предметам
            $subjectStats = $query->clone()->with('subject:id,name')
                ->get()
                ->groupBy('subject_id')
                ->map(function($group) {
                    $subject = $group->first()->subject;
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();

                    return [
                        'subject' => $subject->only(['id', 'name']),
                        'total_records' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'excused' => $group->where('status', 'excused')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values();

            // Статистика по ученикам
            $studentStats = $query->clone()->with('student:id,name,email')
                ->get()
                ->groupBy('student_id')
                ->map(function($group) {
                    $student = $group->first()->student;
                    $total = $group->count();
                    $present = $group->where('status', 'present')->count();

                    return [
                        'student' => $student->only(['id', 'name', 'email']),
                        'total_lessons' => $total,
                        'present' => $present,
                        'absent' => $group->where('status', 'absent')->count(),
                        'late' => $group->where('status', 'late')->count(),
                        'excused' => $group->where('status', 'excused')->count(),
                        'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                    ];
                })->values();

            // Месячная статистика
            $monthlyStats = $query->clone()
                ->selectRaw('
                    DATE_FORMAT(date, "%Y-%m") as month,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present
                ')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->map(function($item) {
                    $item->attendance_percentage = $item->total_records > 0
                        ? round(($item->present / $item->total_records) * 100, 2)
                        : 0;
                    return $item;
                });

            $statistics = [
                'overview' => [
                    'total_records' => $totalRecords,
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'late' => $lateCount,
                    'excused' => $excusedCount,
                    'overall_attendance_percentage' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0
                ],
                'by_subjects' => $subjectStats,
                'by_students' => $studentStats,
                'monthly' => $monthlyStats
            ];

            return response()->json($statistics);

        } catch (\Exception $e) {
            Log::error('Error in AttendanceController::teacherStatistics', [
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении статистики посещаемости'], 500);
        }
    }

    // Получить учеников класса по уроку (автоматическое определение класса из расписания)
    public function getStudentsByLesson(Request $request, $lessonNumber)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            // Проверяем, что пользователь является учителем
            if ($user->role !== 'teacher') {
                return response()->json(['error' => 'Доступ запрещен. Только для учителей.'], 403);
            }

            // Получаем текущий день недели (1-7, понедельник=1, воскресенье=7)
            $currentDayOfWeek = now()->dayOfWeekIso; // 1 = понедельник, 7 = воскресенье

            // Ищем урок в расписании учителя на текущий день
            $schedule = Schedule::where('teacher_id', $teacherId)
                ->where('lesson_number', $lessonNumber)
                ->where('day_of_week', $currentDayOfWeek)
                ->where('is_active', true)
                ->where(function($query) {
                    $query->whereNull('effective_from')
                          ->orWhere('effective_from', '<=', now()->toDateString());
                })
                ->where(function($query) {
                    $query->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', now()->toDateString());
                })
                ->first();

            if (!$schedule) {
                return response()->json(['error' => 'Урок не найден в вашем расписании на сегодня'], 404);
            }

            // Получаем класс из расписания
            $classId = $schedule->school_class_id;
            $class = SchoolClass::findOrFail($classId);

            // Получаем учеников класса
            $students = $class->students()
                ->select('users.id', 'users.name', 'users.surname', 'users.second_name')
                ->orderBy('users.surname')
                ->orderBy('users.name')
                ->get()
                ->map(function($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'surname' => $student->surname,
                        'second_name' => $student->second_name,
                        'full_name' => $student->full_name,
                    ];
                });

            return response()->json([
                'class' => $class->only(['id', 'name', 'year', 'letter']),
                'lesson_number' => $lessonNumber,
                'subject' => $schedule->subject->only(['id', 'name']),
                'students' => $students,
                'total_students' => $students->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in AttendanceController::getStudentsByLesson', [
                'teacher_id' => $user->id ?? null,
                'lesson_number' => $lessonNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении списка учеников'], 500);
        }
    }

    // Сохранить посещаемость по уроку (автоматическое определение класса и предмета)
    public function saveByLesson(Request $request)
    {
        try {
            Log::info('saveByLesson: START - Request received', [
                'lesson_number' => $request->input('lesson_number'),
                'date' => $request->input('date'),
                'school_class_id' => $request->input('school_class_id'),
                'attendance_data_count' => count($request->input('attendance_data', [])),
                'all_request_data' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'lesson_number' => 'required|integer|min:1|max:8',
                'date' => 'required|date',
                'school_class_id' => 'sometimes|required|exists:school_classes,id',
                'attendance_data' => 'required|array|min:1',
                'attendance_data.*.student_id' => 'required|exists:users,id',
                'attendance_data.*.status' => 'required|in:present,absent,late,excused',
                'attendance_data.*.reason' => 'nullable|string|max:500',
                'attendance_data.*.comment' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                Log::warning('saveByLesson: Validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Получаем текущего пользователя
            $user = $request->attributes->get('user');
            if (!$user || $user->role !== 'teacher') {
                return response()->json(['error' => 'Доступ запрещен. Только для учителей.'], 403);
            }

            $teacherId = $user->id;

            // Определяем день недели из даты (1-7, понедельник=1, воскресенье=7)
            $date = \Carbon\Carbon::parse($request->date);
            $dayOfWeek = $date->dayOfWeekIso;

            // Определяем class_id: либо из запроса (если передан), либо из первого студента
            $classId = null;
            if ($request->has('school_class_id') && !empty($request->school_class_id)) {
                $classId = $request->school_class_id;
            } else {
                // Определяем class_id из первого ученика (предполагаем, что все ученики из одного класса)
                $firstStudentId = $request->attendance_data[0]['student_id'];
                $firstStudent = User::findOrFail($firstStudentId);
                if ($firstStudent->role !== 'student') {
                    return response()->json(['error' => 'Пользователь не является учеником'], 400);
                }

                $studentClass = $firstStudent->studentClasses()->first();
                if (!$studentClass) {
                    return response()->json(['error' => 'Ученик не принадлежит ни одному классу'], 400);
                }
                $classId = $studentClass->school_class_id;
            }

            // Проверяем, что все ученики из одного класса
            foreach ($request->attendance_data as $data) {
                $student = User::findOrFail($data['student_id']);
                if ($student->role !== 'student') {
                    return response()->json(['error' => 'Все пользователи должны быть учениками'], 400);
                }
                if (!$student->studentClasses()->where('school_class_id', $classId)->exists()) {
                    return response()->json(['error' => 'Все ученики должны принадлежать одному классу'], 400);
                }
            }

            // Ищем расписание для этого урока
            $schedule = Schedule::where('teacher_id', $teacherId)
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
                Log::error('saveByLesson: Schedule not found', [
                    'teacher_id' => $teacherId,
                    'class_id' => $classId,
                    'lesson_number' => $request->lesson_number,
                    'day_of_week' => $dayOfWeek,
                    'date' => $request->date,
                ]);
                return response()->json(['error' => 'Урок не найден в вашем расписании на указанную дату'], 404);
            }

            $subjectId = $schedule->subject_id;

            // Сохраняем записи посещаемости
            $created = [];
            $errors = [];

            foreach ($request->attendance_data as $data) {
                try {
                    Log::info('Processing attendance for student', [
                        'student_id' => $data['student_id'],
                        'status' => $data['status']
                    ]);

                    // Проверяем дубликаты
                    $existing = Attendance::where('student_id', $data['student_id'])
                        ->where('subject_id', $subjectId)
                        ->where('date', $request->date)
                        ->where('lesson_number', $request->lesson_number)
                        ->first();

                    if ($existing) {
                        $student = User::findOrFail($data['student_id']);
                        $errors[] = "Запись для ученика {$student->name} уже существует";
                        Log::warning('Duplicate attendance record found', [
                            'student_id' => $data['student_id'],
                            'existing_id' => $existing->id
                        ]);
                        continue;
                    }

                    $attendanceData = array_merge($data, [
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherId,
                        'date' => $request->date,
                        'lesson_number' => $request->lesson_number
                    ]);

                    Log::info('About to create attendance record', [
                        'attendance_data' => $attendanceData,
                        'teacher_id' => $teacherId
                    ]);

                    $attendance = Attendance::create($attendanceData);

                    Log::info('Attendance record created successfully', [
                        'attendance_id' => $attendance->id,
                        'student_id' => $attendance->student_id,
                        'teacher_id' => $attendance->teacher_id,
                        'date' => $attendance->date,
                        'status' => $attendance->status
                    ]);

                    $attendance->load(['student:id,name,email', 'subject:id,name']);
                    $created[] = $attendance;

                    // Отправляем событие о создании посещаемости
                    event(new AttendanceMarked($attendance));

                    // Создаем комментарий в TeacherComments если есть reason/comment
                    if (!empty($data['reason'])) {
                        $reasonText = trim($data['reason']);
                        if (!empty($reasonText)) {
                            try {
                                \App\Models\TeacherComment::create([
                                    'user_id' => $teacherId,
                                    'commentable_type' => 'App\Models\Attendance',
                                    'commentable_id' => $attendance->id,
                                    'content' => $reasonText,
                                    'is_visible_to_student' => true,
                                    'is_visible_to_parent' => false,
                                ]);
                            } catch (\Exception $commentException) {
                                Log::warning('Failed to create TeacherComment for attendance', [
                                    'attendance_id' => $attendance->id,
                                    'error' => $commentException->getMessage()
                                ]);
                                // Не прерываем процесс если не удалось создать комментарий
                            }
                        }
                    }

                    // Создаем уведомления при отсутствии
                    if ($attendance->status === 'absent') {
                        $this->createAbsenceNotification($attendance);
                    }

                } catch (\Exception $e) {
                    $errorMsg = "Ошибка для ученика ID {$data['student_id']}: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    Log::error('Error creating attendance record in saveByLesson', [
                        'student_id' => $data['student_id'],
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('Attendance records saved by lesson', [
                'teacher_id' => $teacherId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'lesson_number' => $request->lesson_number,
                'date' => $request->date,
                'created_count' => count($created),
                'errors_count' => count($errors),
                'errors_list' => $errors
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
            Log::error('Error in AttendanceController::saveByLesson', [
                'teacher_id' => $user->id ?? null,
                'lesson_number' => $request->lesson_number ?? null,
                'date' => $request->date ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при сохранении посещаемости по уроку'], 500);
        }
    }

    // Массовое сохранение посещаемости по уроку (одинаковый статус для всего класса)
    public function bulkSaveByLesson(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lesson_number' => 'required|integer|min:1|max:8',
                'date' => 'required|date',
                'bulk_status' => 'required|in:present,absent,late,excused',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Получаем текущего пользователя
            $user = $request->attributes->get('user');
            if (!$user || $user->role !== 'teacher') {
                return response()->json(['error' => 'Доступ запрещен. Только для учителей.'], 403);
            }

            $teacherId = $user->id;

            // Определяем день недели из даты (1-7, понедельник=1, воскресенье=7)
            $date = \Carbon\Carbon::parse($request->date);
            $dayOfWeek = $date->dayOfWeekIso;

            // Ищем расписание для этого урока
            $schedule = Schedule::where('teacher_id', $teacherId)
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

            // Сохраняем записи посещаемости для всех учеников
            $created = [];
            $errors = [];

            foreach ($students as $student) {
                try {
                    // Проверяем дубликаты
                    $existing = Attendance::where('student_id', $student->id)
                        ->where('subject_id', $subjectId)
                        ->where('date', $request->date)
                        ->where('lesson_number', $request->lesson_number)
                        ->first();

                    if ($existing) {
                        $errors[] = "Запись для ученика {$student->name} уже существует";
                        continue;
                    }

                    $attendanceData = [
                        'student_id' => $student->id,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherId,
                        'date' => $request->date,
                        'lesson_number' => $request->lesson_number,
                        'status' => $request->bulk_status,
                    ];

                    $attendance = Attendance::create($attendanceData);
                    $attendance->load(['student:id,name,email', 'subject:id,name']);
                    $created[] = $attendance;

                    // Отправляем событие о создании посещаемости
                    event(new AttendanceMarked($attendance));

                    // Создаем уведомления при отсутствии
                    if ($attendance->status === 'absent') {
                        $this->createAbsenceNotification($attendance);
                    }

                } catch (\Exception $e) {
                    $errors[] = "Ошибка для ученика {$student->name}: " . $e->getMessage();
                }
            }

            Log::info('Bulk attendance records saved by lesson', [
                'teacher_id' => $teacherId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'lesson_number' => $request->lesson_number,
                'date' => $request->date,
                'bulk_status' => $request->bulk_status,
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
                'bulk_status' => $request->bulk_status
            ]);

        } catch (\Exception $e) {
            Log::error('Error in AttendanceController::bulkSaveByLesson', [
                'teacher_id' => $user->id ?? null,
                'lesson_number' => $request->lesson_number ?? null,
                'date' => $request->date ?? null,
                'bulk_status' => $request->bulk_status ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при массовом сохранении посещаемости по уроку'], 500);
        }
    }
}
