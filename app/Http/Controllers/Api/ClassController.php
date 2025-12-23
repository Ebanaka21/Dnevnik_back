<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    // Получить список всех классов
    public function index(Request $request)
    {
        try {
            $classes = SchoolClass::with(['students', 'subjects', 'lessons'])
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $classes,
                'count' => $classes->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении классов'], 500);
        }
    }

    // Создать новый класс
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50|unique:school_classes,name',
                'description' => 'nullable|string|max:500',
                'academic_year' => 'required|string|max:20',
                'class_teacher_id' => 'nullable|exists:users,id',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $class = SchoolClass::create($validator->validated());

            // Загружаем связанные данные
            $class->load(['classTeacher', 'students', 'subjects']);

            Log::info('Class created successfully', [
                'school_class_id' => $class->id,
                'class_name' => $class->name,
                'academic_year' => $class->academic_year
            ]);

            return response()->json($class, 201);

        } catch (\Exception $e) {
            Log::error('Error creating class', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании класса'], 500);
        }
    }

    // Получить детали конкретного класса
    public function show($id)
    {
        try {
            $class = SchoolClass::with([
                'classTeacher:id,name,email',
                'students:id,name,email,role_id',
                'subjects:id,name,description',
                'lessons' => function($query) {
                    $query->with(['subject:id,name', 'teacher:id,name'])
                          ->orderBy('day_of_week')
                          ->orderBy('lesson_number');
                },
                'schedules' => function($query) {
                    $query->with(['subject:id,name', 'teacher:id,name'])
                          ->orderBy('day_of_week')
                          ->orderBy('lesson_number');
                }
            ])->findOrFail($id);

            return response()->json($class);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::show", [
                'school_class_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Класс не найден'], 404);
        }
    }

    // Обновить класс
    public function update(Request $request, $id)
    {
        try {
            $class = SchoolClass::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:50|unique:school_classes,name,' . $id,
                'description' => 'nullable|string|max:500',
                'academic_year' => 'sometimes|required|string|max:20',
                'class_teacher_id' => 'nullable|exists:users,id',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $class->update($validator->validated());
            $class->load(['classTeacher', 'students', 'subjects']);

            Log::info('Class updated successfully', [
                'school_class_id' => $class->id,
                'updated_fields' => $request->only(['name', 'description', 'academic_year', 'class_teacher_id', 'is_active'])
            ]);

            return response()->json($class);

        } catch (\Exception $e) {
            Log::error('Error updating class', [
                'school_class_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении класса'], 500);
        }
    }

    // Удалить класс
    public function destroy($id)
    {
        try {
            $class = SchoolClass::findOrFail($id);

            // Проверяем, что класс не используется в расписании
            if ($class->schedules()->exists() || $class->lessons()->exists()) {
                return response()->json([
                    'error' => 'Нельзя удалить класс, который используется в расписании'
                ], 400);
            }

            $className = $class->name;
            $class->delete();

            Log::info('Class deleted successfully', [
                'school_class_id' => $id,
                'class_name' => $className
            ]);

            return response()->json(['message' => 'Класс успешно удален']);

        } catch (\Exception $e) {
            Log::error('Error deleting class', [
                'school_class_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении класса'], 500);
        }
    }

    // Добавить ученика в класс
    public function addStudent(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $class = SchoolClass::findOrFail($id);
            $studentId = $request->student_id;

            // Проверяем, что пользователь является учеником
            $student = \App\Models\User::findOrFail($studentId);
            if ($student->role->name !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что ученик еще не в этом классе
            if ($class->students()->where('user_id', $studentId)->exists()) {
                return response()->json(['error' => 'Ученик уже добавлен в этот класс'], 400);
            }

            // Добавляем ученика в класс
            $class->students()->attach($studentId);

            Log::info('Student added to class', [
                'school_class_id' => $id,
                'student_id' => $studentId
            ]);

            return response()->json(['message' => 'Ученик добавлен в класс']);

        } catch (\Exception $e) {
            Log::error('Error adding student to class', [
                'school_class_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при добавлении ученика'], 500);
        }
    }

    // Удалить ученика из класса
    public function removeStudent(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $class = SchoolClass::findOrFail($id);
            $studentId = $request->student_id;

            $class->students()->detach($studentId);

            Log::info('Student removed from class', [
                'school_class_id' => $id,
                'student_id' => $studentId
            ]);

            return response()->json(['message' => 'Ученик удален из класса']);

        } catch (\Exception $e) {
            Log::error('Error removing student from class', [
                'school_class_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении ученика'], 500);
        }
    }

    // Получить учеников класса
    public function students($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $students = $class->students()
                ->with(['role:id,name'])
                ->orderBy('name')
                ->get();

            // Добавляем статистику для каждого ученика
            $studentsWithStats = $students->map(function($student) use ($classId) {
                $attendances = $student->attendances()->whereHas('student.studentClasses', function($q) use ($classId) {
                    $q->where('school_class_id', $classId);
                })->get();

                $grades = $student->grades()
                    ->whereHas('student.studentClasses', function($q) use ($classId) {
                        $q->where('school_class_id', $classId);
                    })->get();

                return [
                    'student' => $student->only(['id', 'name', 'email']),
                    'statistics' => [
                        'total_attendances' => $attendances->count(),
                        'attendance_percentage' => $attendances->count() > 0
                            ? round(($attendances->where('status', 'present')->count() / $attendances->count()) * 100, 2)
                            : 0,
                        'total_grades' => $grades->count(),
                        'average_grade' => $grades->avg('grade') ?: 0
                    ]
                ];
            });

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'students' => $studentsWithStats,
                'total_students' => $studentsWithStats->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::students", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении учеников класса'], 500);
        }
    }

    // Получить учителей класса
    public function teachers($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            // Получаем уникальных учителей, которые ведут уроки в этом классе
            $teachers = \App\Models\User::whereHas('lessons', function($query) use ($classId) {
                $query->where('school_class_id', $classId);
            })->with(['role:id,name', 'subjects:id,name'])->get();

            // Также добавляем классного руководителя если он есть
            if ($class->class_teacher_id) {
                $classTeacher = \App\Models\User::where('id', $class->class_teacher_id)
                    ->with(['role:id,name', 'subjects:id,name'])
                    ->first();

                if ($classTeacher && !$teachers->contains('id', $classTeacher->id)) {
                    $teachers->push($classTeacher);
                }
            }

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'teachers' => $teachers->map(function($teacher) {
                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->name,
                        'email' => $teacher->email,
                        'role' => $teacher->role->name,
                        'subjects' => $teacher->subjects->pluck('name')
                    ];
                }),
                'total_teachers' => $teachers->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::teachers", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении учителей класса'], 500);
        }
    }

    // Получить предметы класса
    public function subjects($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $subjects = $class->subjects()
                ->with(['lessons' => function($query) use ($classId) {
                    $query->where('school_class_id', $classId);
                }])
                ->orderBy('name')
                ->get();

            // Добавляем статистику для каждого предмета
            $subjectsWithStats = $subjects->map(function($subject) use ($classId) {
                $lessonsCount = $subject->lessons->count();
                $grades = \App\Models\Grade::whereHas('student.studentClasses', function($q) use ($classId) {
                    $q->where('school_class_id', $classId);
                })->where('subject_id', $subject->id)->get();

                $attendances = \App\Models\Attendance::whereHas('student.studentClasses', function($q) use ($classId) {
                    $q->where('school_class_id', $classId);
                })->where('subject_id', $subject->id)->get();

                return [
                    'subject' => $subject->only(['id', 'name', 'description']),
                    'statistics' => [
                        'total_lessons' => $lessonsCount,
                        'total_grades' => $grades->count(),
                        'average_grade' => $grades->avg('grade') ?: 0,
                        'attendance_percentage' => $attendances->count() > 0
                            ? round(($attendances->where('status', 'present')->count() / $attendances->count()) * 100, 2)
                            : 0
                    ]
                ];
            });

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'subjects' => $subjectsWithStats,
                'total_subjects' => $subjectsWithStats->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::subjects", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении предметов класса'], 500);
        }
    }

    // Получить расписание класса
    public function schedule($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $schedules = $class->schedules()
                ->with([
                    'subject:id,name',
                    'teacher:id,name,email'
                ])
                ->orderBy('day_of_week')
                ->orderBy('lesson_number')
                ->get();

            // Группируем расписание по дням недели
            $scheduleByDays = $schedules->groupBy('day_of_week')->map(function($daySchedule, $dayOfWeek) {
                return [
                    'day_of_week' => (int) $dayOfWeek,
                    'day_name' => $this->getDayName($dayOfWeek),
                    'lessons' => $daySchedule->map(function($schedule) {
                        return [
                            'lesson_number' => $schedule->lesson_number,
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                            'subject' => $schedule->subject->name,
                            'teacher' => $schedule->teacher->name,
                            'room' => $schedule->room
                        ];
                    })
                ];
            })->values();

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'schedule' => $scheduleByDays,
                'total_lessons_per_week' => $schedules->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::schedule", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении расписания класса'], 500);
        }
    }

    // Получить успеваемость класса
    public function grades($classId)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $grades = \App\Models\Grade::with([
                'student:id,name,email',
                'subject:id,name',
                'gradeType:id,name',
                'teacher:id,name,email'
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

            // Статистика по ученикам
            $studentsStats = $grades->groupBy('student_id')->map(function($studentGrades) {
                $student = $studentGrades->first()->student;
                $totalGrades = $studentGrades->count();
                $averageGrade = $studentGrades->avg('grade');

                return [
                    'student' => $student->only(['id', 'name', 'email']),
                    'total_grades' => $totalGrades,
                    'average_grade' => round($averageGrade, 2),
                    'grades_by_subject' => $studentGrades->groupBy('subject.name')->map(function($subjectGrades) {
                        return [
                            'subject' => $subjectGrades->first()->subject->name,
                            'total_grades' => $subjectGrades->count(),
                            'average_grade' => round($subjectGrades->avg('grade'), 2),
                            'grades' => $subjectGrades->pluck('grade')->toArray()
                        ];
                    })->values()
                ];
            })->values();

            // Статистика по предметам
            $subjectsStats = $grades->groupBy('subject_id')->map(function($subjectGrades) {
                $subject = $subjectGrades->first()->subject;
                $totalGrades = $subjectGrades->count();
                $averageGrade = $subjectGrades->avg('grade');

                return [
                    'subject' => $subject->only(['id', 'name']),
                    'total_grades' => $totalGrades,
                    'average_grade' => round($averageGrade, 2),
                    'grade_distribution' => [
                        '5' => $subjectGrades->where('grade', 5)->count(),
                        '4' => $subjectGrades->where('grade', 4)->count(),
                        '3' => $subjectGrades->where('grade', 3)->count(),
                        '2' => $subjectGrades->where('grade', 2)->count()
                    ]
                ];
            })->values();

            // Общая статистика
            $overallStats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('grade'), 2),
                'grade_distribution' => [
                    '5' => $grades->where('grade', 5)->count(),
                    '4' => $grades->where('grade', 4)->count(),
                    '3' => $grades->where('grade', 3)->count(),
                    '2' => $grades->where('grade', 2)->count()
                ],
                'by_students' => $studentsStats,
                'by_subjects' => $subjectsStats
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'grades' => $grades,
                'statistics' => $overallStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ClassController::grades", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении успеваемости класса'], 500);
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
