<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\TeacherComment;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\Notification;
use App\Models\HomeworkSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class TeacherController extends Controller
{
    /**
     * Get the authenticated teacher user.
     */
    private function getAuthenticatedTeacher(Request $request)
    {
        // Используем 'user' из атрибутов запроса, который устанавливается в middleware
        return $request->attributes->get('user');
    }

    /**
     * Get the IDs of classes the teacher is assigned to based on schedule entries.
     */
    private function getTeacherClassIds($teacherId)
    {
        return \App\Models\Schedule::where('teacher_id', $teacherId)
            ->distinct('school_class_id')
            ->pluck('school_class_id');
    }

    /**
     * Get the main dashboard statistics for the teacher.
     */
    public function dashboard(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $teacherId = $teacher->id;

            // Get class IDs from schedule entries instead of curriculum plans
            $classIds = \App\Models\Schedule::where('teacher_id', $teacherId)
                ->distinct('school_class_id')
                ->pluck('school_class_id');

            $stats = [
                'total_classes' => $classIds->count(),
                'total_students' => DB::table('student_classes')
                    ->whereIn('school_class_id', $classIds)
                    ->where('is_active', true)
                    ->count(), // Count all records, not distinct students
                'grades_this_month' => Grade::where('teacher_id', $teacherId)->whereMonth('created_at', now()->month)->count(),
                'active_homework' => Homework::where('teacher_id', $teacherId)->where('due_date', '>=', now())->count(),
                'pending_homework_reviews' => HomeworkSubmission::whereHas('homework', fn($q) => $q->where('teacher_id', $teacherId))->where('status', 'submitted')->count(),
            ];

            $recentGradesRaw = Grade::where('teacher_id', $teacherId)
                ->with(['student:id,name,surname,second_name', 'subject:id,name'])
                ->latest()->take(5)->get();

            // Форматируем данные для фронтенда
            $recentGrades = $recentGradesRaw->map(function ($grade) {
                $student = $grade->student;
                $subject = $grade->subject;
                $gradeType = $grade->gradeType;

                return [
                    'id' => $grade->id,
                    'student_id' => $grade->student_id,
                    'student_name' => $student ? $student->getFullNameAttribute() : 'Неизвестный ученик',
                    'class_name' => '10А', // Пока хардкодим, можно получить из studentClasses
                    'subject_name' => $subject ? $subject->name : 'Неизвестный предмет',
                    'grade_value' => $grade->value,
                    'date' => $grade->date,
                    'grade_type_name' => $gradeType ? $gradeType->name : 'Обычная',
                    'is_class_teacher_student' => true, // Пока true
                ];
            });

            $recentAttendance = Attendance::where('teacher_id', $teacherId)
                ->with(['student:id,name', 'subject:id,name'])
                ->latest()->take(5)->get();

            $notifications = Notification::where('user_id', $teacherId)
                ->latest()->take(5)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'recent_grades' => $recentGrades,
                    'recent_attendance' => $recentAttendance,
                    'notifications' => $notifications,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::dashboard', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve dashboard data.'], 500);
        }
    }

    /**
     * Get the classes taught by the teacher based on schedule entries.
     */
    public function getClasses(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            Log::info("TeacherController::getClasses - Getting classes for teacher ID: {$teacher->id}");

            // Use the same query as ScheduleController::teacherSchedule
            $query = \App\Models\Schedule::with([
                'subject:id,name',
                'schoolClass:id,name,academic_year,class_teacher_id',
                'schoolClass.classTeacher:id,name'
            ])->where('teacher_id', $teacher->id);

            $schedules = $query->orderBy('day_of_week')->orderBy('lesson_number')->get();

            Log::info("TeacherController::getClasses - Found " . $schedules->count() . " schedule entries");

            // Group by class
            $classesById = [];
            foreach ($schedules as $schedule) {
                $classId = $schedule->school_class_id;
                Log::info("TeacherController::getClasses - Processing class ID: {$classId}, subject: " . ($schedule->subject ? $schedule->subject->name : 'null') . ", schoolClass: " . ($schedule->schoolClass ? $schedule->schoolClass->name : 'null'));

                if (!isset($classesById[$classId])) {
                    if (!$schedule->schoolClass) {
                        Log::error("TeacherController::getClasses - schoolClass is null for class ID: {$classId}");
                        continue; // Skip entries with null schoolClass
                    }
                    $classesById[$classId] = [
                        'class' => $schedule->schoolClass,
                        'subjects' => [],
                        'lesson_slots' => [],
                        'lessons_per_week' => 0
                    ];
                }
                // Add subject if not already added
                if ($schedule->subject && !in_array($schedule->subject->name, $classesById[$classId]['subjects'])) {
                    $classesById[$classId]['subjects'][] = $schedule->subject->name;
                }
                // Count unique lesson slots (day + lesson number)
                $slot = $schedule->day_of_week . '-' . $schedule->lesson_number;
                if (!in_array($slot, $classesById[$classId]['lesson_slots'])) {
                    $classesById[$classId]['lesson_slots'][] = $slot;
                    $classesById[$classId]['lessons_per_week']++;
                }
            }

            Log::info("TeacherController::getClasses - Grouped into " . count($classesById) . " classes");

            // Format classes for frontend
            $classes = [];
            foreach ($classesById as $classId => $data) {
                $class = $data['class'];

                // Count students in this class
                $studentCount = DB::table('student_classes')
                    ->where('school_class_id', $classId)
                    ->where('is_active', true)
                    ->count();

                $classes[] = [
                    'id' => $class->id,
                    'name' => $class->name,
                    'academic_year' => $class->academic_year ?? '2024-2025',
                    'student_count' => $studentCount,
                    'average_grade' => 0, // Will be calculated later if needed
                    'attendance_rate' => 0, // Will be calculated later if needed
                    'active_homework' => 0, // Will be calculated later if needed
                    'is_class_teacher' => $class->class_teacher_id === $teacher->id,
                    'status' => 'active', // Assume active for now
                    'subjects' => $data['subjects'],
                    'schedule' => [], // Can be populated if needed
                    'lessons_per_week' => $data['lessons_per_week'],
                    'class_teacher_name' => optional($class->classTeacher)->getFullNameAttribute(),
                    'students' => [], // Can be populated if needed
                    'subjects_with_teacher' => [] // Can be populated if needed
                ];
            }

            // Sort classes by name
            usort($classes, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            Log::info("TeacherController::getClasses - Returning " . count($classes) . " classes");

            return response()->json($classes);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClasses', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve classes.'], 500);
        }
    }

    /**
     * Get detailed information for a specific class.
     */
    public function getClassDetails(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $classIds = $this->getTeacherClassIds($teacher->id);

            if (!$classIds->contains($classId)) {
                return response()->json(['error' => 'Access denied to this class.'], 403);
            }

            $class = SchoolClass::with(['students:id,name,email', 'subjects' => function ($query) use ($teacher) {
                $query->whereHas('teachers', fn($q) => $q->where('user_id', $teacher->id));
            }])->findOrFail($classId);

            return response()->json($class);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassDetails', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class details.'], 500);
        }
    }

    /**
     * Get students for a specific class.
     */
    public function getClassStudents(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $classIds = $this->getTeacherClassIds($teacher->id);

            if (!$classIds->contains($classId)) {
                return response()->json(['error' => 'Access denied to this class.'], 403);
            }

            $students = User::whereHas('studentClasses', fn($q) => $q->where('school_class_id', $classId))
                ->select('id', 'name', 'email')
                ->paginate(30);

            return response()->json($students);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassStudents', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class students.'], 500);
        }
    }

    /**
     * Get dashboard statistics.
     */
    public function getStats(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $teacherId = $teacher->id;

            // Get class IDs from schedule entries instead of curriculum plans
            $classIds = \App\Models\Schedule::where('teacher_id', $teacherId)
                ->distinct('school_class_id')
                ->pluck('school_class_id');

            $stats = [
                'total_classes' => $classIds->count(),
                'total_students' => DB::table('student_classes')
                    ->whereIn('school_class_id', $classIds)
                    ->where('is_active', true)
                    ->count(), // Count all records
                'weekly_grades' => Grade::where('teacher_id', $teacherId)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'active_homework' => Homework::where('teacher_id', $teacherId)->where('due_date', '>=', now())->count(),
                'completed_homework' => Homework::where('teacher_id', $teacherId)->where('due_date', '<', now())->count(),
                'is_class_teacher' => SchoolClass::where('class_teacher_id', $teacherId)->exists(),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getStats', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve statistics.'], 500);
        }
    }

    /**
     * Get recent grades.
     */
    public function getRecentGrades(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $limit = $request->input('limit', 5);

            $grades = Grade::where('teacher_id', $teacher->id)
                ->with([
                    'student:id,name,surname,second_name',
                    'subject:id,name',
                    'gradeType:id,name'
                ])
                ->latest()
                ->latest()
                ->take($limit)
                ->get();

            // Форматируем данные для фронтенда
            $formattedGrades = $grades->map(function ($grade) {
                $student = $grade->student;
                $subject = $grade->subject;
                $gradeType = $grade->gradeType;

                // Получаем класс ученика через отдельный запрос
                $studentClass = DB::table('student_classes')
                    ->join('school_classes', 'student_classes.school_class_id', '=', 'school_classes.id')
                    ->where('student_classes.student_id', $grade->student_id)
                    ->where('student_classes.is_active', true)
                    ->select('school_classes.name')
                    ->first();

                $className = $studentClass ? $studentClass->name : 'Неизвестный класс';

                return [
                    'id' => $grade->id,
                    'student_id' => $grade->student_id,
                    'student_name' => $student ? $student->getFullNameAttribute() : 'Неизвестный ученик',
                    'class_name' => $className,
                    'subject_name' => $subject ? $subject->name : 'Неизвестный предмет',
                    'grade_value' => $grade->value,
                    'date' => $grade->date,
                    'grade_type_name' => $gradeType ? $gradeType->name : 'Обычная',
                    'is_class_teacher_student' => true,
                ];
            });

            return response()->json($formattedGrades);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getRecentGrades', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to retrieve recent grades.'], 500);
        }
    }

    /**
     * Get recent attendance records.
     */
    public function getRecentAttendance(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $limit = $request->input('limit', 10);

            $attendance = Attendance::where('teacher_id', $teacher->id)
                ->with(['student:id,name', 'subject:id,name'])
                ->latest('date')
                ->take($limit)
                ->get();

            return response()->json($attendance);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getRecentAttendance', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve recent attendance.'], 500);
        }
    }

    /**
     * Get grades for the teacher.
     */
    public function getGrades(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            if (!$teacher) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Grade::where('teacher_id', $teacher->id)->with(['student:id,name,surname,second_name', 'subject:id,name', 'gradeType:id,name']);

            // Filters
            if ($request->class_id) {
                $query->whereHas('student.studentClasses', fn($q) => $q->where('school_class_id', $request->class_id));
            }

            if ($request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->date_from) {
                $query->where('date', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('date', '<=', $request->date_to);
            }

            if ($request->student_id) {
                $query->where('student_id', $request->student_id);
            }

            $sortBy = $request->sort_by ?: 'date';
            $sortOrder = $request->sort_order ?: 'desc';

            $grades = $query->orderBy($sortBy, $sortOrder)->paginate(50);

            // Получаем типы оценок отдельно для надежности
            $gradeItems = $grades->items();
            $gradeTypeIds = collect($gradeItems)->pluck('grade_type_id')->filter()->unique();
            $gradeTypes = \App\Models\GradeType::whereIn('id', $gradeTypeIds)->pluck('name', 'id');

            // Добавляем grade_type_name к каждой оценке
            $formattedGrades = collect($gradeItems)->map(function ($grade) use ($gradeTypes) {
                $gradeArray = $grade->toArray();
                $gradeArray['grade_type_name'] = $gradeTypes->get($grade->grade_type_id, 'Неизвестный тип');
                return $gradeArray;
            });

            return response()->json([
                'data' => $formattedGrades,
                'pagination' => [
                    'current_page' => $grades->currentPage(),
                    'last_page' => $grades->lastPage(),
                    'per_page' => $grades->perPage(),
                    'total' => $grades->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getGrades', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve grades.'], 500);
        }
    }

    /**
     * Create a grade for the teacher.
     */
    public function createGrade(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            Log::info('TeacherController::createGrade - Request data', [
                'teacher_id' => $teacher->id,
                'student_id' => $request->student_id,
                'subject_id' => $request->subject_id,
                'grade_type_id' => $request->grade_type_id,
                'grade_value' => $request->grade_value,
                'request_all' => $request->all()
            ]);

            $request->validate([
                'student_id' => 'required|exists:users,id',
                'subject_id' => 'required|exists:subjects,id',
                'grade_type_id' => 'required|exists:grade_types,id',
                'grade_value' => 'required|integer|min:2|max:5',
                'date' => 'required|date',
                'comment' => 'nullable|string',
            ]);

            Log::info('TeacherController::createGrade - Validation passed');

            // Check if student is in a class that teacher teaches
            $studentClass = DB::table('student_classes')
                ->where('student_id', $request->student_id)
                ->where('is_active', true)
                ->first();

            Log::info('TeacherController::createGrade - Student class check', [
                'student_id' => $request->student_id,
                'found' => $studentClass ? true : false,
                'class_id' => $studentClass ? $studentClass->school_class_id : null
            ]);

            if (!$studentClass) {
                return response()->json(['error' => 'Student is not enrolled in any class.'], 400);
            }

            // Check if teacher teaches in this class (using teacher_classes, teacher_subjects, or schedules)
            $teacherClassesRecord = DB::table('teacher_classes')
                ->where('teacher_id', $teacher->id)
                ->where('school_class_id', $studentClass->school_class_id)
                ->where('subject_id', $request->subject_id)
                ->first();

            $teachesInClass = !!$teacherClassesRecord;

            Log::info('TeacherController::createGrade - Teacher classes check', [
                'teacher_id' => $teacher->id,
                'class_id' => $studentClass->school_class_id,
                'subject_id' => $request->subject_id,
                'found_in_teacher_classes' => $teachesInClass,
                'teacher_classes_count' => DB::table('teacher_classes')->where('teacher_id', $teacher->id)->count()
            ]);

            // Alternative: check via teacher_subjects
            if (!$teachesInClass) {
                $teachesInClass = DB::table('teacher_subjects')
                    ->where('teacher_id', $teacher->id)
                    ->where('subject_id', $request->subject_id)
                    ->exists();

                Log::info('TeacherController::createGrade - Teacher subjects check', [
                    'found_in_teacher_subjects' => $teachesInClass,
                    'teacher_subjects_count' => DB::table('teacher_subjects')->where('teacher_id', $teacher->id)->count()
                ]);
            }

            // Alternative: check via schedules
            if (!$teachesInClass) {
                $teachesInClass = DB::table('schedules')
                    ->where('teacher_id', $teacher->id)
                    ->where('school_class_id', $studentClass->school_class_id)
                    ->where('subject_id', $request->subject_id)
                    ->exists();

                Log::info('TeacherController::createGrade - Schedules check', [
                    'found_in_schedules' => $teachesInClass,
                    'schedules_count' => DB::table('schedules')->where('teacher_id', $teacher->id)->where('school_class_id', $studentClass->school_class_id)->count()
                ]);
            }

            if (!$teachesInClass) {
                Log::error('TeacherController::createGrade - Access denied', [
                    'teacher_id' => $teacher->id,
                    'class_id' => $studentClass->school_class_id,
                    'subject_id' => $request->subject_id,
                    'debug_info' => [
                        'teacher_classes_total' => DB::table('teacher_classes')->where('teacher_id', $teacher->id)->get()->toArray(),
                        'teacher_subjects_total' => DB::table('teacher_subjects')->where('teacher_id', $teacher->id)->get()->toArray(),
                        'all_schedules' => DB::table('schedules')->where('teacher_id', $teacher->id)->get()->toArray(),
                    ]
                ]);
                return response()->json(['error' => 'You do not teach this student in this subject.'], 403);
            }

            $grade = Grade::create([
                'student_id' => $request->student_id,
                'subject_id' => $request->subject_id,
                'teacher_id' => $teacher->id,
                'grade_type_id' => $request->grade_type_id,
                'value' => $request->grade_value,
                'date' => $request->date,
                'comment' => $request->comment,
                'school_class_id' => $studentClass->school_class_id
            ]);

            return response()->json($grade->load(['student:id,name,surname,second_name', 'subject:id,name', 'gradeType:id,name']));
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::createGrade', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create grade.'], 500);
        }
    }

    /**
     * Get notifications for the teacher.
     */
    public function getNotifications(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $notifications = Notification::where('user_id', $teacher->id)
                ->latest()
                ->paginate(15);

            return response()->json($notifications);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getNotifications', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve notifications.'], 500);
        }
    }

    /**
     * Get homework statistics.
     */
    public function getHomeworkStats(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $teacherId = $teacher->id;

            $stats = [
                'active_homework' => Homework::where('teacher_id', $teacherId)->where('due_date', '>=', now())->count(),
                'pending_review' => HomeworkSubmission::whereHas('homework', fn($q) => $q->where('teacher_id', $teacherId))->where('status', 'submitted')->count(),
                'completed_homework' => Homework::where('teacher_id', $teacherId)->where('due_date', '<', now())->count(),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getHomeworkStats', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve homework statistics.'], 500);
        }
    }

    /**
     * Get grade types.
     */
    public function getGradeTypes()
    {
        $types = \App\Models\GradeType::all();
        return response()->json($types);
    }

    /**
     * Get detailed class information.
     */
    public function getClassDetail(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            $class = SchoolClass::with('classTeacher:id,name,surname,second_name')->findOrFail($classId);

            // Count students in this class
            $studentCount = DB::table('student_classes')
                ->where('school_class_id', $classId)
                ->where('is_active', true)
                ->count();

            Log::info("Student count for class {$classId}: {$studentCount}");

            // Add subjects taught by this teacher in this class
            $subjects = Subject::whereIn('id', function ($query) use ($teacher, $classId) {
                $query->select('subject_id')
                    ->from('teacher_classes')
                    ->where('teacher_id', $teacher->id)
                    ->where('school_class_id', $classId);
            })->select('id', 'name')->get();

            // Format response for frontend
            $classData = [
                'id' => $class->id,
                'name' => $class->name,
                'academic_year' => $class->academic_year,
                'student_count' => $studentCount,
                'class_teacher_name' => $class->classTeacher ? $class->classTeacher->getFullNameAttribute() : null,
                'is_class_teacher' => $class->class_teacher_id === $teacher->id,
                'status' => $class->status ?? 'active',
                'created_at' => $class->created_at,
                'subjects' => $subjects,
            ];

            return response()->json($classData);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassDetail', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class detail.'], 500);
        }
    }


    /**
     * Ensure test data exists in database.
     */
    private function ensureTestData()
    {
        try {
            Log::info('ensureTestData called');

            // Check if data already exists
            if (DB::table('student_classes')->where('school_class_id', 1)->exists()) {
                Log::info('Test data already exists');
                return;
            }

            Log::info('Creating test data');

            // Create classes if they don't exist
            $classes = [
                ['id' => 1, 'name' => '1А класс', 'year' => 1, 'letter' => 'А', 'academic_year' => '2024-2025', 'class_teacher_id' => 1, 'is_active' => true],
            ];

            foreach ($classes as $classData) {
                SchoolClass::updateOrCreate(['id' => $classData['id']], $classData);
            }

            // Create students if they don't exist
            $students = [
                ['id' => 101, 'name' => 'Иван', 'surname' => 'Иванов', 'second_name' => 'Иванович', 'email' => 'ivanov@student.com', 'role' => 'student'],
                ['id' => 102, 'name' => 'Анна', 'surname' => 'Петрова', 'second_name' => 'Сергеевна', 'email' => 'petrova@student.com', 'role' => 'student'],
                ['id' => 103, 'name' => 'Петр', 'surname' => 'Сидоров', 'second_name' => 'Алексеевич', 'email' => 'sidorov@student.com', 'role' => 'student'],
                ['id' => 104, 'name' => 'Мария', 'surname' => 'Козлова', 'second_name' => 'Дмитриевна', 'email' => 'kozlova@student.com', 'role' => 'student'],
                ['id' => 105, 'name' => 'Алексей', 'surname' => 'Морозов', 'second_name' => 'Владимирович', 'email' => 'morozov@student.com', 'role' => 'student'],
            ];

            foreach ($students as $studentData) {
                User::updateOrCreate(['id' => $studentData['id']], $studentData);
            }

            // Create student-class relationships
            $studentClasses = [
                ['student_id' => 101, 'school_class_id' => 1, 'academic_year' => '2024-2025', 'is_active' => true],
                ['student_id' => 102, 'school_class_id' => 1, 'academic_year' => '2024-2025', 'is_active' => true],
                ['student_id' => 103, 'school_class_id' => 1, 'academic_year' => '2024-2025', 'is_active' => true],
                ['student_id' => 104, 'school_class_id' => 1, 'academic_year' => '2024-2025', 'is_active' => true],
                ['student_id' => 105, 'school_class_id' => 1, 'academic_year' => '2024-2025', 'is_active' => true],
            ];

            foreach ($studentClasses as $scData) {
                DB::table('student_classes')->updateOrInsert(
                    ['student_id' => $scData['student_id'], 'school_class_id' => $scData['school_class_id'], 'academic_year' => $scData['academic_year']],
                    $scData
                );
            }

            // Create subjects
            $subjects = [
                ['id' => 1, 'name' => 'Математика', 'short_name' => 'Мат', 'subject_code' => 'MATH'],
                ['id' => 2, 'name' => 'Русский язык', 'short_name' => 'Рус', 'subject_code' => 'RUS'],
                ['id' => 3, 'name' => 'История', 'short_name' => 'Ист', 'subject_code' => 'HIST'],
            ];

            foreach ($subjects as $subjectData) {
                Subject::updateOrCreate(['id' => $subjectData['id']], $subjectData);
            }

            // Create teacher-class relationships
            $teacherClasses = [
                ['teacher_id' => 1, 'school_class_id' => 1, 'subject_id' => 1],
                ['teacher_id' => 1, 'school_class_id' => 1, 'subject_id' => 2],
            ];

            foreach ($teacherClasses as $tcData) {
                DB::table('teacher_classes')->updateOrInsert(
                    ['teacher_id' => $tcData['teacher_id'], 'school_class_id' => $tcData['school_class_id'], 'subject_id' => $tcData['subject_id']],
                    $tcData
                );
            }

            // Create curriculum plans
            foreach ($teacherClasses as $tcData) {
                \App\Models\CurriculumPlan::updateOrCreate(
                    ['teacher_id' => $tcData['teacher_id'], 'school_class_id' => $tcData['school_class_id'], 'subject_id' => $tcData['subject_id']],
                    ['hours_per_week' => 4]
                );
            }

            // Create schedule
            $scheduleData = [
                ['school_class_id' => 1, 'subject_id' => 1, 'teacher_id' => 1, 'day_of_week' => 1, 'lesson_number' => 1, 'start_time' => '09:00', 'end_time' => '09:45', 'academic_year' => '2024-2025', 'is_active' => true],
                ['school_class_id' => 1, 'subject_id' => 2, 'teacher_id' => 1, 'day_of_week' => 1, 'lesson_number' => 2, 'start_time' => '10:00', 'end_time' => '10:45', 'academic_year' => '2024-2025', 'is_active' => true],
                ['school_class_id' => 1, 'subject_id' => 3, 'teacher_id' => 1, 'day_of_week' => 2, 'lesson_number' => 1, 'start_time' => '09:00', 'end_time' => '09:45', 'academic_year' => '2024-2025', 'is_active' => true],
                ['school_class_id' => 1, 'subject_id' => 1, 'teacher_id' => 1, 'day_of_week' => 3, 'lesson_number' => 1, 'start_time' => '09:00', 'end_time' => '09:45', 'academic_year' => '2024-2025', 'is_active' => true],
                ['school_class_id' => 1, 'subject_id' => 2, 'teacher_id' => 1, 'day_of_week' => 4, 'lesson_number' => 1, 'start_time' => '09:00', 'end_time' => '09:45', 'academic_year' => '2024-2025', 'is_active' => true],
                ['school_class_id' => 1, 'subject_id' => 1, 'teacher_id' => 1, 'day_of_week' => 5, 'lesson_number' => 1, 'start_time' => '09:00', 'end_time' => '09:45', 'academic_year' => '2024-2025', 'is_active' => true],
            ];

            foreach ($scheduleData as $scheduleItem) {
                Schedule::updateOrCreate(
                    [
                        'school_class_id' => $scheduleItem['school_class_id'],
                        'day_of_week' => $scheduleItem['day_of_week'],
                        'lesson_number' => $scheduleItem['lesson_number']
                    ],
                    $scheduleItem
                );
            }

            Log::info('Test data created successfully');
        } catch (\Exception $e) {
            Log::error('Error in ensureTestData: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed students information for a class.
     */
    public function getClassStudentsDetail(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            // Ensure test data exists
            $this->ensureTestData();

            // Get students enrolled in this class
            $students = DB::table('users')
                ->join('student_classes', 'users.id', '=', 'student_classes.student_id')
                ->where('student_classes.school_class_id', $classId)
                ->where('student_classes.is_active', true)
                ->select('users.id', 'users.name', 'users.surname', 'users.second_name')
                ->selectRaw('(SELECT AVG(value) FROM grades WHERE grades.student_id = users.id AND grades.teacher_id = ?) as average_grade', [$teacher->id])
                ->selectRaw('(SELECT COUNT(*) FROM grades WHERE grades.student_id = users.id AND grades.teacher_id = ?) as grades_count', [$teacher->id])
                ->selectRaw('(SELECT COUNT(*) FROM attendance WHERE attendance.student_id = users.id AND attendance.teacher_id = ? AND attendance.status = "present") * 100.0 / NULLIF((SELECT COUNT(*) FROM attendance WHERE attendance.student_id = users.id AND attendance.teacher_id = ?), 0) as attendance_rate', [$teacher->id, $teacher->id])
                ->get();

            Log::info("Found " . $students->count() . " students for class {$classId}");

            // Format students for frontend
            $formattedStudents = $students->map(function ($student) {
                $averageGrade = $student->average_grade ?? 0;
                $attendanceRate = $student->attendance_rate ?? 0;

                // Determine status based on average grade
                if ($averageGrade >= 4.5) {
                    $status = 'excellent';
                } elseif ($averageGrade >= 3.5) {
                    $status = 'good';
                } elseif ($averageGrade >= 2.5) {
                    $status = 'average';
                } else {
                    $status = 'struggling';
                }

                // Create full name from parts
                $fullNameParts = array_filter([$student->surname, $student->name, $student->second_name]);
                $fullName = trim(implode(' ', $fullNameParts)) ?: 'Неизвестный ученик';

                return [
                    'id' => $student->id,
                    'name' => $fullName,
                    'average_grade' => round($averageGrade, 1),
                    'attendance_rate' => round($attendanceRate, 1),
                    'grades_count' => $student->grades_count ?? 0,
                    'status' => $status,
                ];
            });

            return response()->json($formattedStudents);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassStudentsDetail', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class students detail.'], 500);
        }
    }

    /**
     * Get recent grades for a class.
     */
    public function getClassRecentGrades(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $classIds = $this->getTeacherClassIds($teacher->id);

            if (!$classIds->contains($classId)) {
                // Check if teacher is class teacher
                $class = SchoolClass::find($classId);
                if (!$class || $class->class_teacher_id != $teacher->id) {
                    return response()->json(['success' => false, 'error' => 'Class not found or access denied'], 404);
                }
            }

            $grades = Grade::where('teacher_id', $teacher->id)
                ->whereHas('student.studentClasses', fn($q) => $q->where('school_class_id', $classId))
                ->with(['student:id,name,surname,second_name', 'subject:id,name'])
                ->latest()
                ->take(10)
                ->get();

            return response()->json($grades);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassRecentGrades', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class recent grades.'], 500);
        }
    }

    /**
     * Get homework for a class.
     */
    public function getClassHomework(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $classIds = $this->getTeacherClassIds($teacher->id);

            if (!$classIds->contains($classId)) {
                // Check if teacher is class teacher
                $class = SchoolClass::find($classId);
                if (!$class || $class->class_teacher_id != $teacher->id) {
                    return response()->json(['success' => false, 'error' => 'Class not found or access denied'], 404);
                }
            }

            $homework = Homework::where('teacher_id', $teacher->id)
                ->where('school_class_id', $classId)
                ->with(['subject:id,name', 'submissions' => function ($query) {
                    $query->select('id', 'homework_id', 'status')->latest();
                }])
                ->latest()
                ->take(10)
                ->get();

            return response()->json($homework);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassHomework', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class homework.'], 500);
        }
    }

    /**
     * Get statistics for a class.
     */
    public function getClassStatistics(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            // Get all students in the class
            $studentIds = User::whereHas('studentClasses', fn($q) => $q->where('school_class_id', $classId))
                ->pluck('id');

            // Average grade for the class taught by this teacher
            $averageGrade = Grade::where('teacher_id', $teacher->id)
                ->whereHas('student.studentClasses', fn($q) => $q->where('school_class_id', $classId))
                ->avg('value') ?? 0;

            // Attendance rate
            $totalAttendance = Attendance::where('teacher_id', $teacher->id)
                ->whereHas('student.studentClasses', fn($q) => $q->where('school_class_id', $classId))
                ->count();

            $presentAttendance = Attendance::where('teacher_id', $teacher->id)
                ->whereHas('student.studentClasses', fn($q) => $q->where('school_class_id', $classId))
                ->where('status', 'present')
                ->count();

            $attendanceRate = $totalAttendance > 0 ? ($presentAttendance / $totalAttendance) * 100 : 0;

            // Active homework
            $activeHomework = Homework::where('teacher_id', $teacher->id)
                ->where('school_class_id', $classId)
                ->where('due_date', '>=', now())
                ->count();

            // Student performance categories
            $excellentStudents = 0;
            $goodStudents = 0;
            $averageStudents = 0;
            $poorStudents = 0;
            $strugglingStudents = 0;

            foreach ($studentIds as $studentId) {
                $studentAvgGrade = Grade::where('teacher_id', $teacher->id)
                    ->where('student_id', $studentId)
                    ->avg('value') ?? 0;

                if ($studentAvgGrade >= 4.5) {
                    $excellentStudents++;
                } elseif ($studentAvgGrade >= 3.5) {
                    $goodStudents++;
                } elseif ($studentAvgGrade >= 2.5) {
                    $averageStudents++;
                } else {
                    $poorStudents++;
                    if ($studentAvgGrade < 3.0) {
                        $strugglingStudents++;
                    }
                }
            }

            $statistics = [
                'average_grade' => round($averageGrade, 1),
                'attendance_rate' => round($attendanceRate, 1),
                'active_homework' => $activeHomework,
                'excellent_students' => $excellentStudents,
                'struggling_students' => $strugglingStudents,
                'grade_distribution' => [
                    'excellent' => $excellentStudents,
                    'good' => $goodStudents,
                    'average' => $averageStudents,
                    'poor' => $poorStudents,
                ],
            ];

            return response()->json($statistics);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassStatistics', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class statistics.'], 500);
        }
    }

    /**
     * Get schedule for a specific class.
     */
    public function getClassSchedule(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            // Ensure test data exists
            $this->ensureTestData();

            // Get schedule for this class
            $schedule = DB::table('schedules')
                ->join('subjects', 'schedules.subject_id', '=', 'subjects.id')
                ->join('school_classes', 'schedules.class_id', '=', 'school_classes.id')
                ->where('schedules.class_id', $classId)
                ->where('schedules.teacher_id', $teacher->id)
                ->select(
                    'schedules.id',
                    'schedules.day_of_week',
                    'schedules.start_time',
                    'schedules.end_time',
                    'subjects.name as subject_name',
                    'school_classes.name as class_name'
                )
                ->orderBy('schedules.day_of_week')
                ->orderBy('schedules.start_time')
                ->get();

            Log::info("Found " . $schedule->count() . " schedule entries for class {$classId}");

            // Group by day of week
            $weeklySchedule = [];
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            foreach ($days as $day) {
                $daySchedule = $schedule->where('day_of_week', $day)->values();
                $weeklySchedule[$day] = [
                    'day' => $day,
                    'lessons' => $daySchedule->map(function ($lesson) {
                        return [
                            'id' => $lesson->id,
                            'subject' => $lesson->subject_name,
                            'start_time' => $lesson->start_time,
                            'end_time' => $lesson->end_time,
                        ];
                    }),
                    'lesson_count' => $daySchedule->count(),
                ];
            }

            return response()->json($weeklySchedule);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassSchedule', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class schedule.'], 500);
        }
    }

    public function getTeacherSchedule(Request $request)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);

            if (!$teacher) {
                Log::error('TeacherController::getTeacherSchedule - Teacher not authenticated');
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            Log::info('TeacherController::getTeacherSchedule - Fetching schedule for teacher ID: ' . $teacher->id);

            $schedule = Schedule::where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->where('academic_year', '2024-2025')
                ->with([
                    'subject:id,name',
                    'schoolClass:id,name',
                ])
                ->orderBy('day_of_week')
                ->orderBy('lesson_number')
                ->get();

            Log::info('TeacherController::getTeacherSchedule - Found ' . $schedule->count() . ' schedule items');

            return response()->json($schedule);
        } catch (\Exception $e) {
            Log::error('TeacherController::getTeacherSchedule - Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve teacher schedule.'], 500);
        }
    }

    /**
     * Get subjects for a class taught by the teacher.
     */
    public function getClassSubjects(Request $request, $classId)
    {
        try {
            $teacher = $this->getAuthenticatedTeacher($request);
            $classIds = $this->getTeacherClassIds($teacher->id);

            if (!$classIds->contains($classId)) {
                // Check if teacher is class teacher
                $class = SchoolClass::find($classId);
                if (!$class || $class->class_teacher_id != $teacher->id) {
                    return response()->json(['success' => false, 'error' => 'Class not found or access denied'], 404);
                }
            }

            $subjects = Subject::whereIn('id', function ($query) use ($teacher, $classId) {
                $query->select('subject_id')
                    ->from('teacher_classes')
                    ->where('teacher_id', $teacher->id)
                    ->where('school_class_id', $classId);
            })
            ->select('id', 'name')
            ->get();

            return response()->json($subjects);
        } catch (\Exception $e) {
            Log::error('Error in TeacherController::getClassSubjects', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to retrieve class subjects.'], 500);
        }
    }
}
