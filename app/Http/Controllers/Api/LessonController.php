<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LessonController extends Controller
{
    /**
     * Получить список уроков с фильтрацией
     */
    public function index(Request $request)
    {
        try {
            $query = Lesson::with([
                'subject:id,name,short_name',
                'schoolClass:id,name,grade',
                'teacher:id,name,email'
            ]);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по классу
            if ($request->has('class_id') && !empty($request->class_id)) {
                $query->where('class_id', $request->class_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по дате
            if ($request->has('date') && !empty($request->date)) {
                $query->whereDate('date', $request->date);
            }

            // Фильтрация по диапазону дат
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('date', '<=', $request->date_to);
            }

            // Фильтрация по номеру урока
            if ($request->has('lesson_number') && !empty($request->lesson_number)) {
                $query->where('lesson_number', $request->lesson_number);
            }

            // Поиск по названию или описанию
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $lessons = $query->paginate(20);

            return response()->json([
                'data' => $lessons->items(),
                'pagination' => [
                    'current_page' => $lessons->currentPage(),
                    'last_page' => $lessons->lastPage(),
                    'per_page' => $lessons->perPage(),
                    'total' => $lessons->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in LessonController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении уроков'], 500);
        }
    }

    /**
     * Создать новый урок
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subject_id' => 'required|exists:subjects,id',
                'school_class_id' => 'required|exists:school_classes,id',
                'teacher_id' => 'required|exists:users,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'date' => 'required|date',
                'lesson_number' => 'nullable|integer|min:1|max:8',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'homework_assignment' => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем, что пользователь является учителем
            $teacher = User::findOrFail($request->teacher_id);
            if ($teacher->role->name !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            $lesson = Lesson::create($validator->validated());

            // Загружаем связанные данные
            $lesson->load([
                'subject:id,name,short_name',
                'schoolClass:id,name,grade',
                'teacher:id,name,email'
            ]);

            Log::info('Lesson created successfully', [
                'lesson_id' => $lesson->id,
                'subject_id' => $lesson->subject_id,
                'school_class_id' => $lesson->class_id,
                'teacher_id' => $lesson->teacher_id,
                'date' => $lesson->date
            ]);

            return response()->json($lesson, 201);

        } catch (\Exception $e) {
            Log::error('Error creating lesson', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании урока'], 500);
        }
    }

    /**
     * Получить детали конкретного урока
     */
    public function show($id)
    {
        try {
            $lesson = Lesson::with([
                'subject:id,name,short_name,description',
                'schoolClass:id,name,grade',
                'teacher:id,name,email',
                'attendances.student:id,name,email',
                'schedules'
            ])->findOrFail($id);

            // Добавляем статистику посещаемости
            $attendanceStats = [
                'total_students' => $lesson->attendances->count(),
                'present_count' => $lesson->attendances->where('status', 'present')->count(),
                'absent_count' => $lesson->attendances->where('status', 'absent')->count(),
                'late_count' => $lesson->attendances->where('status', 'late')->count(),
                'excused_count' => $lesson->attendances->where('status', 'excused')->count(),
            ];

            return response()->json([
                'lesson' => $lesson,
                'attendance_stats' => $attendanceStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in LessonController::show", [
                'lesson_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Урок не найден'], 404);
        }
    }

    /**
     * Обновить урок
     */
    public function update(Request $request, $id)
    {
        try {
            $lesson = Lesson::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'subject_id' => 'sometimes|required|exists:subjects,id',
                'school_class_id' => 'sometimes|required|exists:school_classes,id',
                'teacher_id' => 'sometimes|required|exists:users,id',
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'date' => 'sometimes|required|date',
                'lesson_number' => 'nullable|integer|min:1|max:8',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'homework_assignment' => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Если изменяется учитель, проверяем права
            if ($request->has('teacher_id') && $request->teacher_id !== $lesson->teacher_id) {
                $teacher = User::findOrFail($request->teacher_id);
                if ($teacher->role->name !== 'teacher') {
                    return response()->json(['error' => 'Пользователь не является учителем'], 400);
                }
            }

            $lesson->update($validator->validated());
            $lesson->load([
                'subject:id,name,short_name',
                'schoolClass:id,name,grade',
                'teacher:id,name,email'
            ]);

            Log::info('Lesson updated successfully', [
                'lesson_id' => $lesson->id,
                'updated_fields' => array_keys($validator->validated())
            ]);

            return response()->json($lesson);

        } catch (\Exception $e) {
            Log::error('Error updating lesson', [
                'lesson_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении урока'], 500);
        }
    }

    /**
     * Удалить урок
     */
    public function destroy($id)
    {
        try {
            $lesson = Lesson::findOrFail($id);

            // Проверяем, есть ли связанные данные (посещаемость)
            if ($lesson->attendances()->exists()) {
                return response()->json([
                    'error' => 'Нельзя удалить урок, так как существуют записи посещаемости'
                ], 400);
            }

            $lesson->delete();

            Log::info('Lesson deleted successfully', [
                'lesson_id' => $id
            ]);

            return response()->json(['message' => 'Урок успешно удален']);

        } catch (\Exception $e) {
            Log::error('Error deleting lesson', [
                'lesson_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении урока'], 500);
        }
    }

    /**
     * Получить уроки на дату
     */
    public function byDate($date)
    {
        try {
            $lessons = Lesson::with([
                'subject:id,name,short_name',
                'schoolClass:id,name,grade',
                'teacher:id,name,email'
            ])->whereDate('date', $date)
              ->orderBy('lesson_number')
              ->orderBy('start_time')
              ->get();

            // Группируем уроки по классам
            $lessonsByClass = $lessons->groupBy('schoolClass.name')->map(function($classLessons) {
                return [
                    'class' => $classLessons->first()->schoolClass->only(['id', 'name', 'grade']),
                    'lessons' => $classLessons
                ];
            });

            // Группируем уроки по учителям
            $lessonsByTeacher = $lessons->groupBy('teacher.name')->map(function($teacherLessons) {
                return [
                    'teacher' => $teacherLessons->first()->teacher->only(['id', 'name', 'email']),
                    'lessons' => $teacherLessons
                ];
            });

            return response()->json([
                'date' => $date,
                'total_lessons' => $lessons->count(),
                'lessons' => $lessons,
                'grouped_by_class' => $lessonsByClass->values(),
                'grouped_by_teacher' => $lessonsByTeacher->values()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in LessonController::byDate", [
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении уроков на дату'], 500);
        }
    }

    /**
     * Получить уроки класса
     */
    public function byClass($classId)
    {
        try {
            $schoolClass = SchoolClass::findOrFail($classId);

            $query = Lesson::with([
                'subject:id,name,short_name',
                'teacher:id,name,email'
            ])->where('class_id', $classId);

            // Фильтрация по датам
            if (request()->has('date_from') && !empty(request()->date_from)) {
                $query->whereDate('date', '>=', request()->date_from);
            }

            if (request()->has('date_to') && !empty(request()->date_to)) {
                $query->whereDate('date', '<=', request()->date_to);
            }

            $lessons = $query->orderBy('date', 'desc')
                            ->orderBy('lesson_number')
                            ->get();

            // Добавляем статистику по предметам
            $subjectStats = $lessons->groupBy('subject.name')->map(function($subjectLessons) {
                $subject = $subjectLessons->first()->subject;
                return [
                    'subject' => $subject->only(['id', 'name', 'short_name']),
                    'total_lessons' => $subjectLessons->count(),
                    'lessons' => $subjectLessons
                ];
            })->values();

            // Статистика по учителям
            $teacherStats = $lessons->groupBy('teacher.name')->map(function($teacherLessons) {
                $teacher = $teacherLessons->first()->teacher;
                return [
                    'teacher' => $teacher->only(['id', 'name', 'email']),
                    'total_lessons' => $teacherLessons->count(),
                    'lessons' => $teacherLessons
                ];
            })->values();

            return response()->json([
                'class' => $schoolClass->only(['id', 'name', 'grade']),
                'total_lessons' => $lessons->count(),
                'lessons' => $lessons,
                'subject_statistics' => $subjectStats,
                'teacher_statistics' => $teacherStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in LessonController::byClass", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Класс не найден или ошибка при получении уроков'], 404);
        }
    }
}
