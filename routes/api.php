<?php

use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\TeacherCommentController;
use App\Http\Controllers\Api\CurriculumPlanController;
use App\Http\Controllers\Api\TeacherController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes - Электронный дневник школьника
|--------------------------------------------------------------------------
|
| Все маршруты здесь имеют префикс /api автоматически
| Защищённые маршруты используют middleware simple.jwt
|
*/

// ==================== ПУБЛИЧНЫЕ МАРШРУТЫ (без авторизации) ====================

// Авторизация
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// ==================== ЗАЩИЩЁННЫЕ МАРШРУТЫ (требуют JWT) ====================

Route::middleware('simple.jwt')->group(function () {

    // Пользователь
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ==================== УПРАВЛЕНИЕ КЛАССАМИ ====================
    Route::middleware('role:admin,teacher')->apiResource('classes', ClassController::class);

    // ==================== УПРАВЛЕНИЕ ПРЕДМЕТАМИ ====================
    Route::prefix('subjects')->middleware('rate.limit:60,1')->group(function () {
        Route::get('/', [SubjectController::class, 'index'])->middleware('role:admin,teacher');
        Route::post('/', [SubjectController::class, 'store'])->middleware('role:admin');
        Route::get('/{id}', [SubjectController::class, 'show'])->middleware('role:admin,teacher');
        Route::put('/{id}', [SubjectController::class, 'update'])->middleware('role:admin');
        Route::delete('/{id}', [SubjectController::class, 'destroy'])->middleware('role:admin');
        Route::get('/teacher/{teacherId}', [SubjectController::class, 'byTeacher'])->middleware('role:admin,teacher');
        Route::get('/class/{classId}', [SubjectController::class, 'byClass'])->middleware('role:admin,teacher');
    });

    // ==================== ДВУХФАКТОРНАЯ АУТЕНТИФИКАЦИЯ ====================
    Route::prefix('two-factor')->group(function () {
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
        Route::post('/send-code', [TwoFactorController::class, 'sendCode']);
        Route::post('/verify-code', [TwoFactorController::class, 'verifyCode']);
        Route::post('/complete-login', [TwoFactorController::class, 'completeLogin']);
    });

    // ==================== УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ ====================
    Route::prefix('users')->group(function () {
        // Ученики - доступны админам и учителям (ПЕРЕД админской группой!)
        Route::get('/students', [UserManagementController::class, 'students'])->middleware('role:admin,teacher');

        // Админские методы
        Route::middleware('role:admin')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/teachers', [UserManagementController::class, 'teachers']);
            Route::get('/parents', [UserManagementController::class, 'parents']);
            Route::post('/', [UserManagementController::class, 'store']);
            Route::get('/{user}', [UserManagementController::class, 'show']);
            Route::put('/{user}', [UserManagementController::class, 'update']);
            Route::delete('/{user}', [UserManagementController::class, 'destroy']);
        });

        // Методы для связи родителей и учеников - доступны админам и родителям
        Route::post('/link-parent-student', [UserManagementController::class, 'linkParentStudent'])->middleware('role:admin,parent');
        Route::post('/unlink-parent-student', [UserManagementController::class, 'unlinkParentStudent'])->middleware('role:admin,parent');

        // Методы для связи учеников и классов - только админы
        Route::post('/link-student-class', [UserManagementController::class, 'linkStudentClass'])->middleware('role:admin');
        Route::post('/unlink-student-class', [UserManagementController::class, 'unlinkStudentClass'])->middleware('role:admin');
    });

    // ==================== ОЦЕНКИ ====================
    Route::prefix('grades')->group(function () {
        // Получение оценок - доступно всем ролям
        Route::get('/', [GradeController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/student/{studentId}', [GradeController::class, 'studentGrades'])->middleware('role:admin,teacher,student,parent');
        Route::get('/subject/{subjectId}', [GradeController::class, 'subjectGrades'])->middleware('role:admin,teacher');
        Route::get('/{grade}', [GradeController::class, 'show'])->middleware('role:admin,teacher,student,parent');

        // Создание/редактирование оценок - только учителя и администраторы
        Route::post('/', [GradeController::class, 'store'])->middleware('role:admin,teacher');
        Route::put('/{grade}', [GradeController::class, 'update'])->middleware('role:admin,teacher');
        Route::delete('/{grade}', [GradeController::class, 'destroy'])->middleware('role:admin,teacher');

        // Специализированные методы для родителей
        Route::get('/parent/{studentId}', [GradeController::class, 'getChildGrades'])->middleware('role:parent');
    });

    // ==================== ПОСЕЩАЕМОСТЬ ====================
    Route::prefix('attendance')->group(function () {
        // Получение посещаемости - доступно всем ролям
        Route::get('/', [AttendanceController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/student/{studentId}', [AttendanceController::class, 'studentAttendance'])->middleware('role:admin,teacher,student,parent');
        Route::get('/class/{classId}', [AttendanceController::class, 'classAttendance'])->middleware('role:admin,teacher');
        Route::get('/{attendance}', [AttendanceController::class, 'show'])->middleware('role:admin,teacher,student,parent');

        // Создание/редактирование посещаемости - только учителя и администраторы
        Route::post('/', [AttendanceController::class, 'store'])->middleware('role:admin,teacher');
        Route::put('/{attendance}', [AttendanceController::class, 'update'])->middleware('role:admin,teacher');
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy'])->middleware('role:admin,teacher');

        // Массовое создание записей посещаемости
        Route::post('/bulk', [AttendanceController::class, 'bulkCreate'])->middleware('role:admin,teacher');

        // Специализированные методы для родителей
        Route::get('/parent/{studentId}', [AttendanceController::class, 'getChildAttendance'])->middleware('role:parent');

        // Получение посещаемости по дате
        Route::get('/date/{date}', [AttendanceController::class, 'byDate'])->middleware('role:admin,teacher');

        // Статистика посещаемости
        Route::get('/statistics/{classId}', [AttendanceController::class, 'statistics'])->middleware('role:admin,teacher');
    });

    // ==================== ДОМАШНИЕ ЗАДАНИЯ ====================
    Route::prefix('homework')->group(function () {
        // Получение домашних заданий - доступно всем ролям
        Route::get('/', [HomeworkController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/class/{classId}', [HomeworkController::class, 'classHomework'])->middleware('role:admin,teacher,student,parent');
        Route::get('/student/{studentId}', [HomeworkController::class, 'studentHomework'])->middleware('role:admin,teacher,student,parent');
        Route::get('/{homework}', [HomeworkController::class, 'show'])->middleware('role:admin,teacher,student,parent');

        // Создание/редактирование домашних заданий - только учителя и администраторы
        Route::post('/', [HomeworkController::class, 'store'])->middleware('role:admin,teacher');
        Route::put('/{homework}', [HomeworkController::class, 'update'])->middleware('role:admin,teacher');
        Route::delete('/{homework}', [HomeworkController::class, 'destroy'])->middleware('role:admin,teacher');

        // Выполнение домашних заданий
        Route::prefix('{homework}/submissions')->group(function () {
            Route::post('/', [HomeworkController::class, 'submitHomework'])->middleware('role:student');
            Route::get('/', [HomeworkController::class, 'submissions'])->middleware('role:admin,teacher');
            Route::put('/{submission}/review', [HomeworkController::class, 'reviewSubmission'])->middleware('role:admin,teacher');
            Route::post('/create-or-update', [HomeworkController::class, 'createOrUpdateSubmission'])->middleware('role:admin,teacher');
        });

        // Специализированные методы для родителей
        Route::get('/parent/{studentId}', [HomeworkController::class, 'getChildHomeworks'])->middleware('role:parent');

        // Дополнительные методы
        Route::get('/subject/{subjectId}', [HomeworkController::class, 'bySubject'])->middleware('role:admin,teacher');
        Route::get('/teacher/{teacherId}', [HomeworkController::class, 'byTeacher'])->middleware('role:admin,teacher');
        Route::get('/student/{studentId}/pending', [HomeworkController::class, 'pending'])->middleware('role:admin,teacher,student');

        // Метод для получения невыполненных заданий
        Route::get('/pending/{studentId}', [HomeworkController::class, 'pending'])->middleware('role:admin,teacher,student');

        // Метод для сдачи домашнего задания
        Route::post('/{homeworkId}/submit', [HomeworkController::class, 'submitHomework'])->middleware('role:student');

        // Метод для получения статистики
        Route::get('/statistics/{classId}', [HomeworkController::class, 'statistics'])->middleware('role:admin,teacher');

        // Метод для получения заданий по классу
        Route::get('/class/{classId}', [HomeworkController::class, 'classHomework'])->middleware('role:admin,teacher,student,parent');
    });

    // ==================== УЧИТЕЛЬ - ПАНЕЛЬ УПРАВЛЕНИЯ ====================
    Route::prefix('teacher/dashboard')->group(function () {
        Route::get('/', [TeacherController::class, 'dashboard'])->middleware('role:teacher,admin');
        Route::get('/stats', [TeacherController::class, 'getStats'])->middleware('role:teacher,admin');
        Route::get('/recent-grades', [TeacherController::class, 'getRecentGrades'])->middleware('role:teacher,admin');
        Route::get('/recent-attendance', [TeacherController::class, 'getRecentAttendance'])->middleware('role:teacher,admin');
        Route::get('/notifications', [TeacherController::class, 'getNotifications'])->middleware('role:teacher,admin');
        Route::get('/homework-stats', [TeacherController::class, 'getHomeworkStats'])->middleware('role:teacher,admin');
    });

    // ==================== УЧИТЕЛЬ - УПРАВЛЕНИЕ КЛАССАМИ ====================
    Route::prefix('teacher/classes')->group(function () {
        Route::get('/', [TeacherController::class, 'getClasses'])->middleware('role:teacher,admin');
        Route::get('/{classId}', [TeacherController::class, 'getClassDetails'])->middleware('role:teacher,admin');
        Route::get('/{classId}/students', [TeacherController::class, 'getClassStudents'])->middleware('role:teacher,admin');
        Route::get('/{classId}/schedule', [TeacherController::class, 'getClassSchedule'])->middleware('role:teacher,admin');
        Route::get('/{classId}/subjects', [TeacherController::class, 'getClassSubjects'])->middleware('role:teacher,admin');
        Route::get('/{classId}/detail', [TeacherController::class, 'getClassDetail'])->middleware('role:teacher,admin');
        Route::get('/{classId}/students-detail', [TeacherController::class, 'getClassStudentsDetail'])->middleware('role:teacher,admin');
        Route::get('/{classId}/recent-grades', [TeacherController::class, 'getClassRecentGrades'])->middleware('role:teacher,admin');
        Route::get('/{classId}/homework', [TeacherController::class, 'getClassHomework'])->middleware('role:teacher,admin');
        Route::get('/{classId}/statistics', [TeacherController::class, 'getClassStatistics'])->middleware('role:teacher,admin');
    });

    // ==================== УЧИТЕЛЬ - УПРАВЛЕНИЕ ОЦЕНКАМИ ====================
    Route::prefix('teacher/grades')->middleware('role:teacher,admin')->group(function () {
        Route::get('/', [TeacherController::class, 'getGrades']);
        Route::post('/', [TeacherController::class, 'createGrade']);
        Route::put('/{gradeId}', [TeacherController::class, 'updateGrade']);
        Route::delete('/{gradeId}', [TeacherController::class, 'deleteGrade']);
        Route::get('/types', [GradeController::class, 'types']);
        Route::get('/statistics', [GradeController::class, 'teacherStatistics']);
        Route::post('/save-by-lesson', [GradeController::class, 'saveByLesson']);
        Route::post('/bulk-save-by-lesson', [GradeController::class, 'bulkSaveByLesson']);
    });

    // ==================== УЧИТЕЛЬ - УПРАВЛЕНИЕ ПОСЕЩАЕМОСТЬЮ ====================
    Route::prefix('teacher/attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->middleware('role:teacher,admin');
        Route::post('/', [AttendanceController::class, 'store'])->middleware('role:teacher,admin');
        Route::put('/{id}', [AttendanceController::class, 'update'])->middleware('role:teacher,admin');
        Route::delete('/{id}', [AttendanceController::class, 'destroy'])->middleware('role:teacher,admin');
        Route::get('/classes/{classId}/date/{date}', [AttendanceController::class, 'classAttendanceByDate'])->middleware('role:teacher,admin');
        Route::post('/bulk', [AttendanceController::class, 'bulkCreate'])->middleware('role:teacher,admin');
        Route::get('/statistics', [AttendanceController::class, 'statistics'])->middleware('role:teacher,admin');
        Route::get('/lesson/{lessonNumber}/students', [AttendanceController::class, 'getStudentsByLesson'])->middleware('role:teacher,admin');
        Route::post('/save-by-lesson', [AttendanceController::class, 'saveByLesson'])->middleware('role:teacher,admin');
        Route::post('/bulk-save-by-lesson', [AttendanceController::class, 'bulkSaveByLesson'])->middleware('role:teacher,admin');

        // ТЕСТОВЫЙ ENDPOINT
        Route::post('/test-save', function(\Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::info('TEST SAVE: Получены данные', $request->all());
            return response()->json([
                'status' => 'ok',
                'message' => 'Данные получены',
                'received_data' => $request->all(),
                'timestamp' => now()
            ]);
        })->middleware('role:teacher,admin');

        Route::get('/test-load', function() {
            $allCount = \App\Models\Attendance::count();
            $data = \App\Models\Attendance::limit(5)->get();

            // Получаем статистику по teacher_id
            $teacherStats = \App\Models\Attendance::select('teacher_id', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('teacher_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            $teacherIds = \App\Models\Attendance::distinct()->pluck('teacher_id')->take(20)->toArray();

            // Проверяем наличие конкретного teacher_id
            $teacher209Count = \App\Models\Attendance::where('teacher_id', 209)->count();
            $teacher209CountStr = \App\Models\Attendance::where('teacher_id', '209')->count();

            \Illuminate\Support\Facades\Log::info('TEST LOAD: Статистика по teacher_id', [
                'total_count' => $allCount,
                'returned_count' => $data->count(),
                'teacher_209_count_int' => $teacher209Count,
                'teacher_209_count_string' => $teacher209CountStr,
                'all_teacher_ids' => $teacherIds,
                'teacher_stats' => $teacherStats->toArray()
            ]);

            return response()->json([
                'status' => 'ok',
                'data' => $data,
                'count' => $data->count(),
                'total_in_db' => $allCount,
                'teacher_209_count' => $teacher209Count,
                'teacher_209_count_string' => $teacher209CountStr,
                'all_teacher_ids' => $teacherIds,
                'teacher_stats' => $teacherStats,
                'timestamp' => now()
            ]);
        })->middleware('role:teacher,admin');
    });

    // ==================== УЧИТЕЛЬ - УПРАВЛЕНИЕ ДОМАШНИМИ ЗАДАНИЯМИ ====================
    Route::prefix('teacher/homework')->group(function () {
        Route::get('/', [HomeworkController::class, 'index'])->middleware('role:teacher,admin');
        Route::post('/', [HomeworkController::class, 'store'])->middleware('role:teacher,admin');
        Route::put('/{id}', [HomeworkController::class, 'update'])->middleware('role:teacher,admin');
        Route::put('/{id}/close', [HomeworkController::class, 'close'])->middleware('role:teacher,admin');
        Route::get('/{id}', [HomeworkController::class, 'show'])->middleware('role:teacher,admin');
        Route::delete('/{id}', [HomeworkController::class, 'destroy'])->middleware('role:teacher,admin');
        Route::get('/{id}/submissions', [HomeworkController::class, 'submissions'])->middleware('role:teacher,admin');
        Route::post('/submissions/{submissionId}/review', [HomeworkController::class, 'reviewSubmission'])->middleware('role:teacher,admin');
        Route::get('/statistics', [HomeworkController::class, 'statistics'])->middleware('role:teacher,admin');
        Route::get('/students-by-class/{classId}', [HomeworkController::class, 'getStudentsByClass'])->middleware('role:teacher,admin');
    });

    // ==================== УЧИТЕЛЬ - КЛАССНОЕ РУКОВОДСТВО ====================
    Route::prefix('teacher/class-teacher')->group(function () {
        Route::get('/classes', [TeacherController::class, 'getClassTeacherClasses'])->middleware('role:teacher,admin');
        Route::get('/students', [TeacherController::class, 'getClassTeacherStudents'])->middleware('role:teacher,admin');
        Route::get('/parents', [TeacherController::class, 'getClassTeacherParents'])->middleware('role:teacher,admin');
        Route::post('/comments', [TeacherController::class, 'createStudentComment'])->middleware('role:teacher,admin');
        Route::get('/students/{studentId}/comments', [TeacherController::class, 'getStudentComments'])->middleware('role:teacher,admin');
        Route::get('/classes/{classId}/summary', [TeacherController::class, 'getClassSummary'])->middleware('role:teacher,admin');
        Route::put('/comments/{commentId}', [TeacherController::class, 'updateStudentComment'])->middleware('role:teacher,admin');
        Route::delete('/comments/{commentId}', [TeacherController::class, 'deleteStudentComment'])->middleware('role:teacher,admin');
    });

    // ==================== РАСПИСАНИЕ ====================
    Route::prefix('schedule')->group(function () {
        // Получение расписания - доступно всем ролям
        Route::get('/', [ScheduleController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/class/{classId}', [ScheduleController::class, 'classSchedule'])->middleware('role:admin,teacher,student,parent');
        Route::get('/teacher/{teacherId}', [ScheduleController::class, 'teacherSchedule'])->middleware('role:admin,teacher');
        Route::get('/teacher/{teacherId}/tomorrow', [ScheduleController::class, 'teacherTomorrowSchedule'])->middleware('role:admin,teacher');
        Route::get('/date/{date}', [ScheduleController::class, 'byDate'])->middleware('role:admin,teacher,student,parent');
        Route::get('/{schedule}', [ScheduleController::class, 'show'])->middleware('role:admin,teacher,student,parent');

        // Создание/редактирование расписания - только администраторы и учителя
        Route::post('/', [ScheduleController::class, 'store'])->middleware('role:admin,teacher');
        Route::put('/{schedule}', [ScheduleController::class, 'update'])->middleware('role:admin,teacher');
        Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->middleware('role:admin,teacher');
    });

    // ==================== УВЕДОМЛЕНИЯ (расширенные) ====================
    Route::prefix('notifications')->group(function () {
        // Получение уведомлений пользователя
        Route::get('/', [NotificationController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/', [NotificationController::class, 'getNotifications'])->middleware('role:admin,teacher,student,parent');

        // Получение непрочитанных уведомлений
        Route::get('/unread', [NotificationController::class, 'unread'])->middleware('role:admin,teacher,student,parent');

        // Отметка как прочитанное
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('role:admin,teacher,student,parent');

        // Отметка всех как прочитанных
        Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->middleware('role:admin,teacher,student,parent');
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('role:admin,teacher,student,parent');

        // Создание уведомлений - только для админов и учителей
        Route::post('/', [NotificationController::class, 'store'])->middleware('role:admin,teacher');
        Route::post('/grade', [NotificationController::class, 'createGradeNotification'])->middleware('role:admin,teacher');
        Route::post('/attendance', [NotificationController::class, 'createAttendanceNotification'])->middleware('role:admin,teacher');
        Route::post('/homework', [NotificationController::class, 'createHomeworkNotification'])->middleware('role:admin,teacher');

        // Массовая отправка уведомлений
        Route::post('/bulk', [NotificationController::class, 'sendBulk'])->middleware('role:admin,teacher');

        // Отправка уведомлений классу
        Route::post('/class', [NotificationController::class, 'sendToClass'])->middleware('role:admin,teacher');

        // Удаление уведомления
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->middleware('role:admin,teacher,student,parent');

        // Получение количества непрочитанных
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->middleware('role:admin,teacher,student,parent');

        // Получение последних уведомлений
        Route::get('/recent', [NotificationController::class, 'getRecent'])->middleware('role:admin,teacher,student,parent');

        // Очистка старых уведомлений
        Route::post('/cleanup', [NotificationController::class, 'cleanup'])->middleware('role:admin');
    });

    // ==================== КОММЕНТАРИИ УЧИТЕЛЯ ====================
    Route::prefix('teacher-comments')->group(function () {
        // Получение комментариев - доступно всем ролям
        Route::get('/', [TeacherCommentController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/{id}', [TeacherCommentController::class, 'show'])->middleware('role:admin,teacher,student,parent');

        // Создание/редактирование комментариев - только учителя и администраторы
        Route::post('/', [TeacherCommentController::class, 'store'])->middleware('role:admin,teacher');
        Route::put('/{id}', [TeacherCommentController::class, 'update'])->middleware('role:admin,teacher');
        Route::delete('/{id}', [TeacherCommentController::class, 'destroy'])->middleware('role:admin,teacher');

        // Специализированные методы
        Route::get('/grade/{gradeId}', [TeacherCommentController::class, 'getByGrade'])->middleware('role:admin,teacher,student,parent');
        Route::get('/homework/{homeworkId}', [TeacherCommentController::class, 'getByHomework'])->middleware('role:admin,teacher,student,parent');
        Route::get('/student/{studentId}', [TeacherCommentController::class, 'getVisibleToStudent'])->middleware('role:admin,teacher,student');
        Route::get('/parent/{parentId}', [TeacherCommentController::class, 'getVisibleToParent'])->middleware('role:admin,teacher,parent');
    });

    // ==================== УЧЕБНЫЕ ПЛАНЫ ====================
    Route::prefix('curriculum-plans')->group(function () {
        // Получение учебных планов - доступно всем ролям
        Route::get('/', [CurriculumPlanController::class, 'index'])->middleware('role:admin,teacher,student,parent');
        Route::get('/{id}', [CurriculumPlanController::class, 'show'])->middleware('role:admin,teacher,student,parent');

        // Создание/редактирование учебных планов - только администраторы
        Route::post('/', [CurriculumPlanController::class, 'store'])->middleware('role:admin');
        Route::put('/{id}', [CurriculumPlanController::class, 'update'])->middleware('role:admin');
        Route::delete('/{id}', [CurriculumPlanController::class, 'destroy'])->middleware('role:admin');

        // Специализированные методы
        Route::get('/class/{classId}', [CurriculumPlanController::class, 'getByClass'])->middleware('role:admin,teacher,student,parent');
        Route::get('/subject/{subjectId}', [CurriculumPlanController::class, 'getBySubject'])->middleware('role:admin,teacher');
        Route::get('/teacher/{teacherId}', [CurriculumPlanController::class, 'byTeacher'])->middleware('role:admin,teacher');

        // Тематические блоки
        Route::get('/{planId}/thematic-blocks', [CurriculumPlanController::class, 'getThematicBlocks'])->middleware('role:admin,teacher');
        Route::post('/{planId}/thematic-blocks', [CurriculumPlanController::class, 'createThematicBlock'])->middleware('role:admin');

        // Недельное планирование
        Route::get('/{planId}/weekly-plan', [CurriculumPlanController::class, 'getWeeklyPlan'])->middleware('role:admin,teacher');
    });

    // ==================== ОТЧЕТЫ (расширенные) ====================
    Route::prefix('reports')->group(function () {
        // Существующие отчеты - доступно всем ролям
        Route::get('/student/{studentId}/grades', [ReportController::class, 'studentGradesReport'])->middleware('role:admin,teacher,student,parent');
        Route::get('/student/{studentId}/attendance', [ReportController::class, 'studentAttendanceReport'])->middleware('role:admin,teacher,student,parent');
        Route::get('/class/{classId}/summary', [ReportController::class, 'classSummaryReport'])->middleware('role:admin,teacher');
        Route::get('/teacher/{teacherId}/workload', [ReportController::class, 'teacherWorkloadReport'])->middleware('role:admin,teacher');

        // Новые методы для отчетов по успеваемости - только для админов и учителей
        Route::post('/performance', [ReportController::class, 'generatePerformanceReport'])->middleware('role:admin,teacher');
        Route::post('/attendance-report', [ReportController::class, 'generateAttendanceReport'])->middleware('role:admin,teacher');

        // Экспорт отчетов - только для админов и учителей
        Route::prefix('export')->group(function () {
            Route::post('/pdf', [ReportController::class, 'exportToPDF'])->middleware('role:admin,teacher');
            Route::post('/excel', [ReportController::class, 'exportToExcel'])->middleware('role:admin,teacher');
        });
    });
});

// ==================== ВНУТРЕННИЕ МАРШРУТЫ ====================

Route::middleware('cliente.token')->group(function () {
    Route::post('/internal/validate', [AuthController::class, 'internalValidate']);
    Route::get('/internal/users', fn() =>
        \App\Models\User::select('id', 'name', 'email', 'created_at')->get()
    );
});

// ==================== ЗДОРОВЬЕ ПРИЛОЖЕНИЯ ====================

Route::get('/health', fn() => response()->json(['status' => 'ok', 'time' => now()]));
