<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    // Получить список всего расписания
    public function index(Request $request)
    {
        try {
            $query = Schedule::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year'
            ]);

            // Фильтрация по классу
            if ($request->has('class_id') && !empty($request->class_id)) {
                $query->where('school_class_id', $request->class_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по дню недели
            if ($request->has('day_of_week') && !empty($request->day_of_week)) {
                $query->where('day_of_week', $request->day_of_week);
            }

            // Сортировка
            $query->orderBy('day_of_week')->orderBy('lesson_number');

            $schedules = $query->get();

            return response()->json([
                'data' => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении расписания'], 500);
        }
    }

    // Создать новую запись расписания
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_class_id' => 'required|exists:school_classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:users,id',
                'day_of_week' => 'required|integer|min:1|max:7',
                'lesson_number' => 'required|integer|min:1|max:8',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'classroom' => 'nullable|string|max:100',
                'is_active' => 'boolean',
                'academic_year' => 'required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
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

            // Проверяем конфликты в расписании
            $conflicts = $this->checkScheduleConflicts($request->all());
            if (!empty($conflicts)) {
                return response()->json([
                    'error' => 'Конфликт в расписании',
                    'conflicts' => $conflicts
                ], 400);
            }

            $schedule = Schedule::create($validator->validated());

            // Загружаем связанные данные
            $schedule->load(['subject:id,name', 'teacher:id,name,email', 'schoolClass:id,name']);

            Log::info('Schedule created successfully', [
                'schedule_id' => $schedule->id,
                'class_id' => $schedule->school_class_id,
                'teacher_id' => $schedule->teacher_id,
                'day_of_week' => $schedule->day_of_week,
                'lesson_number' => $schedule->lesson_number
            ]);

            return response()->json($schedule, 201);

        } catch (\Exception $e) {
            Log::error('Error creating schedule', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании расписания'], 500);
        }
    }

    // Получить расписание класса
    public function classSchedule($classId, Request $request)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $query = Schedule::with([
                'subject:id,name',
                'teacher:id,name,email'
            ])->where('school_class_id', $classId);

            // Фильтрация по дню недели
            if ($request->has('day_of_week') && !empty($request->day_of_week)) {
                $query->where('day_of_week', $request->day_of_week);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            $schedules = $query->orderBy('day_of_week')->orderBy('lesson_number')->get();

            // Группируем по дням недели
            $grouped = $schedules->groupBy('day_of_week')->map(function($daySchedule) {
                return $daySchedule->sortBy('lesson_number')->values();
            });

            $daysOfWeek = [
                1 => 'Понедельник',
                2 => 'Вторник',
                3 => 'Среда',
                4 => 'Четверг',
                5 => 'Пятница',
                6 => 'Суббота',
                7 => 'Воскресенье'
            ];

            $result = [];
            foreach ($daysOfWeek as $dayNumber => $dayName) {
                $result[$dayName] = $grouped->get($dayNumber, collect());
            }

            // Статистика
            $stats = [
                'total_lessons' => $schedules->count(),
                'unique_teachers' => $schedules->pluck('teacher_id')->unique()->count(),
                'unique_subjects' => $schedules->pluck('subject_id')->unique()->count(),
                'lessons_per_day' => $schedules->groupBy('day_of_week')->map->count(),
                'by_teacher' => $schedules->groupBy('teacher_id')->map(function($group) {
                    $teacher = $group->first()->teacher;
                    return [
                        'teacher' => $teacher->name,
                        'lessons_count' => $group->count(),
                        'subjects' => $group->pluck('subject.name')->unique()->toArray()
                    ];
                })->values()
            ];

            return response()->json([
                'data' => [
                    'class' => $class->only(['id', 'name', 'academic_year']),
                    'schedule' => $result,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::classSchedule", [
                'class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении расписания класса'], 500);
        }
    }

    // Получить расписание учителя
    public function teacherSchedule($teacherId, Request $request)
    {
        try {
            Log::info("ScheduleController::teacherSchedule - Starting for teacher ID: {$teacherId}");

            $teacher = User::findOrFail($teacherId);

            Log::info("ScheduleController::teacherSchedule - Teacher found: " . $teacher->email);

            // Проверяем, что пользователь является учителем
            if ($teacher->role !== 'teacher') {
                Log::warning("ScheduleController::teacherSchedule - User is not a teacher: " . $teacher->role);
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            Log::info("ScheduleController::teacherSchedule - Teacher role confirmed");

            $query = Schedule::with([
                'subject:id,name',
                'schoolClass:id,name,academic_year,class_teacher_id',
                'schoolClass.classTeacher:id,name'
            ])->where('teacher_id', $teacherId);

            Log::info("ScheduleController::teacherSchedule - Query built: " . $query->toSql());

            // Фильтрация по дню недели
            if ($request->has('day_of_week') && !empty($request->day_of_week)) {
                $query->where('day_of_week', $request->day_of_week);
            }

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->school_class_id)) {
                $query->where('school_class_id', $request->school_class_id);
            }

            $schedules = $query->orderBy('day_of_week')->orderBy('lesson_number')->get();

            Log::info("ScheduleController::teacherSchedule - Found schedules: " . $schedules->count());

            // Преобразуем расписание в формат, ожидаемый фронтендом
            $formattedSchedules = $schedules->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'school_class_id' => $schedule->school_class_id,
                    'subject_id' => $schedule->subject_id,
                    'teacher_id' => $schedule->teacher_id,
                    'day_of_week' => $schedule->day_of_week,
                    'lesson_number' => $schedule->lesson_number,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'classroom' => $schedule->classroom,
                    'is_active' => $schedule->is_active,
                    'academic_year' => $schedule->academic_year,
                    'subject' => $schedule->subject,
                    'schoolClass' => $schedule->schoolClass,
                    'created_at' => $schedule->created_at,
                    'updated_at' => $schedule->updated_at
                ];
            });

            Log::info("ScheduleController::teacherSchedule - Returning formatted schedules: " . count($formattedSchedules));

            // Возвращаем данные в формате, ожидаемом фронтендом
            return response()->json([
                'success' => true,
                'data' => $formattedSchedules,
                'total_lessons' => $schedules->count()
            ]);

        } catch (\throwable $e) {
            Log::error("Error in ScheduleController::teacherSchedule", [
                'teacher_id' => $teacherId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении расписания учителя: ' . $e->getMessage()], 500);
        }
    }

    // Получить детали конкретной записи расписания
    public function show($id)
    {
        try {
            $schedule = Schedule::with([
                'subject:id,name,description',
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year'
            ])->findOrFail($id);

            return response()->json($schedule);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::show", [
                'schedule_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Запись расписания не найдена'], 404);
        }
    }

    // Обновить запись расписания
    public function update(Request $request, $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'subject_id' => 'sometimes|required|exists:subjects,id',
                'teacher_id' => 'sometimes|required|exists:users,id',
                'day_of_week' => 'sometimes|required|integer|min:1|max:7',
                'lesson_number' => 'sometimes|required|integer|min:1|max:8',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'classroom' => 'nullable|string|max:100',
                'is_active' => 'boolean',
                'academic_year' => 'sometimes|required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Если изменяются ключевые поля, проверяем конфликты
            if (isset($data['teacher_id']) || isset($data['day_of_week']) || isset($data['lesson_number']) || isset($data['start_time'])) {
                $updateData = array_merge($schedule->toArray(), $data);
                $conflicts = $this->checkScheduleConflicts($updateData, $id);

                if (!empty($conflicts)) {
                    return response()->json([
                        'error' => 'Конфликт в расписании',
                        'conflicts' => $conflicts
                    ], 400);
                }
            }

            // Проверяем учителя, если он изменяется
            if (isset($data['teacher_id'])) {
                $teacher = User::findOrFail($data['teacher_id']);
                if ($teacher->role !== 'teacher') {
                    return response()->json(['error' => 'Пользователь не является учителем'], 400);
                }

                $subjectId = $data['subject_id'] ?? $schedule->subject_id;
                if (!$teacher->subjects()->where('subject_id', $subjectId)->exists()) {
                    return response()->json(['error' => 'Учитель не ведет этот предмет'], 400);
                }
            }

            $schedule->update($data);
            $schedule->load(['subject:id,name', 'teacher:id,name,email', 'schoolClass:id,name']);

            Log::info('Schedule updated successfully', [
                'schedule_id' => $schedule->id,
                'updated_fields' => array_keys($data)
            ]);

            return response()->json($schedule);

        } catch (\Exception $e) {
            Log::error('Error updating schedule', [
                'schedule_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении расписания'], 500);
        }
    }

    // Удалить запись расписания
    public function destroy($id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->delete();

            Log::info('Schedule deleted successfully', [
                'schedule_id' => $id
            ]);

            return response()->json(['message' => 'Запись расписания успешно удалена']);

        } catch (\Exception $e) {
            Log::error('Error deleting schedule', [
                'schedule_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении записи расписания'], 500);
        }
    }

    // Проверить конфликты в расписании
    private function checkScheduleConflicts($data, $excludeId = null)
    {
        $conflicts = [];

        try {
            $query = Schedule::where('day_of_week', $data['day_of_week'])
                ->where('lesson_number', $data['lesson_number'])
                ->where('academic_year', $data['academic_year']);

            // Исключаем текущую запись при обновлении
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            // Проверяем конфликт учителя
            if (isset($data['teacher_id'])) {
                $teacherConflict = clone $query;
                if ($teacherConflict->where('teacher_id', $data['teacher_id'])->exists()) {
                    $conflicts[] = 'Учитель уже занят в это время';
                }
            }

            // Проверяем конфликт класса
            if (isset($data['school_class_id'])) {
                $classConflict = clone $query;
                if ($classConflict->where('school_class_id', $data['school_class_id'])->exists()) {
                    $conflicts[] = 'Класс уже занят в это время';
                }
            }

            // Проверяем конфликт аудитории
            if (isset($data['classroom']) && !empty($data['classroom'])) {
                $classroomConflict = clone $query;
                if ($classroomConflict->where('classroom', $data['classroom'])->exists()) {
                    $conflicts[] = 'Аудитория уже занята в это время';
                }
            }

        } catch (\Exception $e) {
            Log::warning('Error checking schedule conflicts', [
                'error' => $e->getMessage()
            ]);
        }

        return $conflicts;
    }

    // Создать расписание для класса на основе шаблона
    public function createFromTemplate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_class_id' => 'required|exists:school_classes,id',
                'academic_year' => 'required|string|max:20',
                'template_data' => 'required|array',
                'template_data.*.subject_id' => 'required|exists:subjects,id',
                'template_data.*.teacher_id' => 'required|exists:users,id',
                'template_data.*.day_of_week' => 'required|integer|min:1|max:7',
                'template_data.*.lesson_number' => 'required|integer|min:1|max:8',
                'template_data.*.start_time' => 'required|date_format:H:i',
                'template_data.*.end_time' => 'required|date_format:H:i',
                'template_data.*.classroom' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $created = [];
            $errors = [];

            foreach ($request->template_data as $index => $lessonData) {
                try {
                    // Проверяем конфликты
                    $lessonData['school_class_id'] = $request->school_class_id;
                    $lessonData['academic_year'] = $request->academic_year;

                    $conflicts = $this->checkScheduleConflicts($lessonData);
                    if (!empty($conflicts)) {
                        $errors[] = "Урок " . ($index + 1) . ": " . implode(', ', $conflicts);
                        continue;
                    }

                    // Проверяем учителя
                    $teacher = User::findOrFail($lessonData['teacher_id']);
                    if ($teacher->role !== 'teacher') {
                        $errors[] = "Урок " . ($index + 1) . ": Пользователь не является учителем";
                        continue;
                    }

                    // Проверяем, что учитель ведет предмет
                    if (!$teacher->subjects()->where('subject_id', $lessonData['subject_id'])->exists()) {
                        $errors[] = "Урок " . ($index + 1) . ": Учитель не ведет этот предмет";
                        continue;
                    }

                    $schedule = Schedule::create($lessonData);
                    $created[] = $schedule;

                } catch (\Exception $e) {
                    $errors[] = "Урок " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            Log::info('Schedule template created', [
                'class_id' => $request->school_class_id,
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
            Log::error('Error creating schedule from template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при создании расписания из шаблона'], 500);
        }
    }

    // Получить шаблон расписания для класса
    public function getTemplate($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $schedules = Schedule::with([
                'subject:id,name',
                'teacher:id,name,email'
            ])->where('school_class_id', $classId)
              ->orderBy('day_of_week')
              ->orderBy('lesson_number')
              ->get();

            // Преобразуем в формат шаблона
            $template = [];
            foreach ($schedules as $schedule) {
                $template[] = [
                    'subject_id' => $schedule->subject_id,
                    'teacher_id' => $schedule->teacher_id,
                    'day_of_week' => $schedule->day_of_week,
                    'lesson_number' => $schedule->lesson_number,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'classroom' => $schedule->classroom,
                ];
            }

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'template' => $template,
                'total_lessons' => count($template)
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::getTemplate", [
                'class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении шаблона расписания'], 500);
        }
    }

    // Получить расписание класса (алиас для classSchedule)
    public function byClass($classId, Request $request)
    {
        return $this->classSchedule($classId, $request);
    }

    // Получить расписание учителя (алиас для teacherSchedule)
    public function byTeacher($teacherId, Request $request)
    {
        return $this->teacherSchedule($teacherId, $request);
    }

    // Получить расписание на дату
    public function byDate($date, Request $request)
    {
        try {
            // Получаем день недели для даты (ISO 8601: 1-7, Понедельник=1, Воскресенье=7)
            $dateObj = \Carbon\Carbon::parse($date);
            $dayOfWeek = $dateObj->dayOfWeekIso;

            Log::info("ScheduleController::byDate - Parsed date {$date} to dayOfWeek {$dayOfWeek}");

            $query = Schedule::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year'
            ])->where('day_of_week', $dayOfWeek)
              ->where('is_active', true);

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->school_class_id)) {
                $query->where('school_class_id', $request->school_class_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
                Log::info("ScheduleController::byDate - Filtering by teacher: " . $request->teacher_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            $schedules = $query->orderBy('lesson_number')->get();

            Log::info("ScheduleController::byDate - Found " . $schedules->count() . " schedules for date {$date}, dayOfWeek {$dayOfWeek}");

            // Статистика
            $stats = [
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'day_name' => $this->getDayName($dayOfWeek),
                'total_lessons' => $schedules->count(),
                'unique_teachers' => $schedules->pluck('teacher_id')->unique()->count(),
                'unique_classes' => $schedules->pluck('school_class_id')->unique()->count(),
                'unique_subjects' => $schedules->pluck('subject_id')->unique()->count(),
                'by_class' => $schedules->groupBy('school_class_id')->map(function($group) {
                    $class = $group->first()->schoolClass;
                    return [
                        'class' => $class->name,
                        'lessons_count' => $group->count(),
                        'lessons' => $group->pluck('lesson_number')->sort()->values()->toArray()
                    ];
                })->values(),
                'by_teacher' => $schedules->groupBy('teacher_id')->map(function($group) {
                    $teacher = $group->first()->teacher;
                    return [
                        'teacher' => $teacher->name,
                        'lessons_count' => $group->count(),
                        'lessons' => $group->pluck('lesson_number')->sort()->values()->toArray()
                    ];
                })->values()
            ];

            return response()->json([
                'data' => $schedules,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::byDate", [
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении расписания на дату'], 500);
        }
    }

    // Получить замены учителей
    public function substitutes(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'day_of_week' => 'nullable|integer|min:1|max:7',
                'teacher_id' => 'nullable|exists:users,id',
                'class_id' => 'nullable|exists:school_classes,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Если указана дата, получаем день недели
            $dayOfWeek = $request->day_of_week;
            if ($request->has('date') && !empty($request->date)) {
                $dateObj = \Carbon\Carbon::parse($request->date);
                $dayOfWeek = $dateObj->dayOfWeek;
                if ($dayOfWeek === 0) {
                    $dayOfWeek = 7;
                }
            }

            if (!$dayOfWeek) {
                return response()->json(['error' => 'Необходимо указать дату или день недели'], 400);
            }

            $query = Schedule::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year'
            ])->where('day_of_week', $dayOfWeek);

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по классу
            if ($request->has('class_id') && !empty($request->class_id)) {
                $query->where('school_class_id', $request->class_id);
            }

            $schedules = $query->orderBy('lesson_number')->get();

            // Анализ нагрузки учителей для поиска возможных замен
            $teacherWorkload = $schedules->groupBy('teacher_id')->map(function($group) {
                $teacher = $group->first()->teacher;
                return [
                    'teacher' => $teacher->only(['id', 'name', 'email']),
                    'total_lessons' => $group->count(),
                    'lessons' => $group->map(function($schedule) {
                        return [
                            'lesson_number' => $schedule->lesson_number,
                            'class' => $schedule->schoolClass->name,
                            'subject' => $schedule->subject->name,
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                            'classroom' => $schedule->classroom
                        ];
                    })
                ];
            })->values();

            // Поиск учителей с меньшей нагрузкой (возможные замены)
            $maxLessonsPerTeacher = $teacherWorkload->max('total_lessons');
            $availableSubstitutes = $teacherWorkload->filter(function($workload) use ($maxLessonsPerTeacher) {
                return $workload['total_lessons'] < $maxLessonsPerTeacher;
            })->values();

            $stats = [
                'date' => $request->date ?? null,
                'day_of_week' => $dayOfWeek,
                'day_name' => $this->getDayName($dayOfWeek),
                'total_lessons' => $schedules->count(),
                'teachers_count' => $teacherWorkload->count(),
                'max_lessons_per_teacher' => $maxLessonsPerTeacher,
                'average_lessons_per_teacher' => round($teacherWorkload->avg('total_lessons'), 2),
                'teachers_with_max_load' => $teacherWorkload->where('total_lessons', $maxLessonsPerTeacher)->count(),
                'available_substitutes' => $availableSubstitutes->count()
            ];

            return response()->json([
                'schedules' => $schedules,
                'teacher_workload' => $teacherWorkload,
                'available_substitutes' => $availableSubstitutes,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::substitutes", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении информации о заменах'], 500);
        }
    }

    // Получить расписание учителя на завтра
    public function teacherTomorrowSchedule($teacherId, Request $request)
    {
        try {
            $teacher = User::findOrFail($teacherId);

            // Проверяем, что пользователь является учителем
            if ($teacher->role !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            // Получаем завтрашнюю дату
            $tomorrow = \Carbon\Carbon::tomorrow();
            $dayOfWeek = $tomorrow->dayOfWeek;

            // В Carbon воскресенье = 0, а у нас = 7
            if ($dayOfWeek === 0) {
                $dayOfWeek = 7;
            }

            $query = Schedule::with([
                'subject:id,name',
                'schoolClass:id,name,academic_year'
            ])->where('teacher_id', $teacherId)
             ->where('day_of_week', $dayOfWeek)
             ->where('is_active', true);

            $schedules = $query->orderBy('lesson_number')->get();

            // Форматируем данные для фронтенда
            $dateString = $tomorrow->toDateString();
            $dateFormatted = $tomorrow->format('d.m.Y');

            $formattedSchedules = $schedules->map(function($schedule) use ($dateString, $dateFormatted) {
                return [
                    'id' => $schedule->id,
                    'subject' => $schedule->subject->name,
                    'class' => $schedule->schoolClass->name,
                    'lesson_number' => $schedule->lesson_number,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'classroom' => $schedule->classroom,
                    'day_of_week' => $schedule->day_of_week,
                    'day_name' => $this->getDayName($schedule->day_of_week),
                    'date' => $dateString,
                    'date_formatted' => $dateFormatted
                ];
            });

            return response()->json([
                'teacher' => $teacher->only(['id', 'name', 'email']),
                'date' => $tomorrow->toDateString(),
                'date_formatted' => $tomorrow->format('d.m.Y'),
                'day_name' => $this->getDayName($dayOfWeek),
                'schedule' => $formattedSchedules,
                'lessons_count' => $formattedSchedules->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ScheduleController::teacherTomorrowSchedule", [
                'teacher_id' => $teacherId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении расписания учителя на завтра'], 500);
        }
    }

    // Вспомогательный метод для получения названия дня недели
    private function getDayName($dayOfWeek)
    {
        $days = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье'
        ];

        return $days[$dayOfWeek] ?? 'Неизвестно';
    }
}
