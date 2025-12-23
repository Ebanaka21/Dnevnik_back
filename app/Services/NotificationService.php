<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Lesson;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Создать уведомление для учителя о том, что не выставлены оценки за урок
     */
    public function createGradesNotSetNotification(Lesson $lesson, User $teacher)
    {
        try {
            $subjectName = $lesson->subject->name ?? 'Неизвестный предмет';
            $className = $lesson->schedule->schoolClass->name ?? 'Неизвестный класс';

            $notification = Notification::create([
                'user_id' => $teacher->id,
                'title' => 'Нет оценок за урок',
                'message' => "По уроку {$subjectName} ({$className}) не выставлены оценки",
                'type' => 'GRADES_NOT_SET',
                'related_id' => $lesson->id,
                'related_type' => 'lesson',
                'is_read' => false,
                'data' => [
                    'lesson_id' => $lesson->id,
                    'school_class_id' => $lesson->schedule->schoolClass->id ?? null,
                    'subject_id' => $lesson->subject->id ?? null,
                    'lesson_date' => $lesson->lesson_date,
                    'lesson_number' => $lesson->lesson_number
                ]
            ]);

            Log::info('Created grades not set notification', [
                'notification_id' => $notification->id,
                'teacher_id' => $teacher->id,
                'lesson_id' => $lesson->id
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating grades not set notification', [
                'lesson_id' => $lesson->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Создать уведомление о том, что не отмечена посещаемость
     */
    public function createAttendanceNotSetNotification(Lesson $lesson, User $teacher)
    {
        try {
            $subjectName = $lesson->subject->name ?? 'Неизвестный предмет';

            $notification = Notification::create([
                'user_id' => $teacher->id,
                'title' => 'Учитель не отметил посещаемость',
                'message' => "Посещаемость по уроку {$subjectName} не заполнена",
                'type' => 'ATTENDANCE_NOT_SET',
                'related_id' => $lesson->id,
                'related_type' => 'lesson',
                'is_read' => false,
                'data' => [
                    'lesson_id' => $lesson->id,
                    'subject_id' => $lesson->subject->id ?? null,
                    'lesson_date' => $lesson->lesson_date,
                    'lesson_number' => $lesson->lesson_number
                ]
            ]);

            Log::info('Created attendance not set notification', [
                'notification_id' => $notification->id,
                'teacher_id' => $teacher->id,
                'lesson_id' => $lesson->id
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating attendance not set notification', [
                'lesson_id' => $lesson->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Создать уведомление об отсутствии ученика
     */
    public function createStudentAbsentNotification(Attendance $attendance, User $teacher)
    {
        try {
            $studentName = $attendance->student->user->name ?? 'Неизвестный ученик';
            $subjectName = $attendance->lesson->subject->name ?? 'Неизвестный предмет';

            $notification = Notification::create([
                'user_id' => $teacher->id,
                'title' => 'Ученик отсутствовал',
                'message' => "Ученик {$studentName} отсутствовал на уроке {$subjectName}",
                'type' => 'STUDENT_ABSENT',
                'related_id' => $attendance->student->id,
                'related_type' => 'student',
                'is_read' => false,
                'data' => [
                    'student_id' => $attendance->student->id,
                    'lesson_id' => $attendance->lesson->id,
                    'attendance_date' => $attendance->attendance_date,
                    'status' => $attendance->status
                ]
            ]);

            Log::info('Created student absent notification', [
                'notification_id' => $notification->id,
                'teacher_id' => $teacher->id,
                'student_id' => $attendance->student->id,
                'attendance_id' => $attendance->id
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating student absent notification', [
                'attendance_id' => $attendance->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Создать уведомление о просроченных домашних заданиях
     */
    public function createHomeworkOverdueNotification(Homework $homework, array $overdueSubmissions = [])
    {
        try {
            $subjectName = $homework->subject->name ?? 'Неизвестный предмет';
            $overdueCount = count($overdueSubmissions);

            $notification = Notification::create([
                'user_id' => $homework->teacher->id,
                'title' => 'Просроченные задания',
                'message' => "По домашнему заданию по {$subjectName} есть {$overdueCount} просроченных сдач",
                'type' => 'HOMEWORK_OVERDUE',
                'related_id' => $homework->id,
                'related_type' => 'homework',
                'is_read' => false,
                'data' => [
                    'homework_id' => $homework->id,
                    'subject_id' => $homework->subject->id ?? null,
                    'overdue_count' => $overdueCount,
                    'due_date' => $homework->due_date,
                    'overdue_submissions' => array_map(function($submission) {
                        return [
                            'student_id' => $submission->student->id,
                            'student_name' => $submission->student->user->name ?? 'Неизвестный',
                            'submission_date' => $submission->created_at
                        ];
                    }, $overdueSubmissions)
                ]
            ]);

            Log::info('Created homework overdue notification', [
                'notification_id' => $notification->id,
                'teacher_id' => $homework->teacher->id,
                'homework_id' => $homework->id,
                'overdue_count' => $overdueCount
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating homework overdue notification', [
                'homework_id' => $homework->id,
                'teacher_id' => $homework->teacher->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Создать уведомление о конфликте оценок
     */
    public function createGradeConflictNotification(Grade $grade, $conflictType = 'duplicate')
    {
        try {
            $studentName = $grade->student->user->name ?? 'Неизвестный ученик';
            $subjectName = $grade->subject->name ?? 'Неизвестный предмет';
            $gradeValue = $grade->grade_value;

            $conflictMessages = [
                'duplicate' => "Обнаружен дубликат оценки {$gradeValue} для {$studentName} по {$subjectName}",
                'invalid' => "Некорректная оценка {$gradeValue} для {$studentName} по {$subjectName}",
                'missing' => "Отсутствует оценка для {$studentName} по {$subjectName}"
            ];

            $message = $conflictMessages[$conflictType] ?? "Обнаружен конфликт оценок для {$studentName} по {$subjectName}";

            $notification = Notification::create([
                'user_id' => $grade->teacher->id,
                'title' => 'Конфликт оценок',
                'message' => $message,
                'type' => 'GRADE_CONFLICT',
                'related_id' => $grade->id,
                'related_type' => 'grade',
                'is_read' => false,
                'data' => [
                    'grade_id' => $grade->id,
                    'student_id' => $grade->student->id,
                    'subject_id' => $grade->subject->id ?? null,
                    'grade_value' => $grade->grade_value,
                    'conflict_type' => $conflictType,
                    'lesson_id' => $grade->lesson_id
                ]
            ]);

            Log::info('Created grade conflict notification', [
                'notification_id' => $notification->id,
                'teacher_id' => $grade->teacher->id,
                'grade_id' => $grade->id,
                'conflict_type' => $conflictType
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating grade conflict notification', [
                'grade_id' => $grade->id,
                'teacher_id' => $grade->teacher->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Создать системное уведомление
     */
    public function createSystemNotification(User $user, $title, $message, $relatedId = null, $relatedType = null)
    {
        try {
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => 'SYSTEM_INFO',
                'related_id' => $relatedId,
                'related_type' => $relatedType,
                'is_read' => false
            ]);

            Log::info('Created system notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'title' => $title
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating system notification', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Создать важное уведомление
     */
    public function createImportantNotification(User $user, $title, $message, $relatedId = null, $relatedType = null)
    {
        try {
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => 'IMPORTANT',
                'related_id' => $relatedId,
                'related_type' => $relatedType,
                'is_read' => false
            ]);

            Log::info('Created important notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'title' => $title
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creating important notification', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Проверить уроки без оценок и создать уведомления
     */
    public function checkLessonsWithoutGrades()
    {
        try {
            $lessons = Lesson::whereDoesntHave('grades')
                ->where('lesson_date', '<=', now()->toDateString())
                ->with(['schedule.teacher', 'subject', 'schedule.schoolClass'])
                ->get();

            foreach ($lessons as $lesson) {
                if ($lesson->schedule && $lesson->schedule->teacher) {
                    $this->createGradesNotSetNotification($lesson, $lesson->schedule->teacher);
                }
            }

            Log::info("Checked lessons without grades, found: " . count($lessons));

        } catch (\Exception $e) {
            Log::error('Error checking lessons without grades', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Проверить уроки без отмеченной посещаемости
     */
    public function checkLessonsWithoutAttendance()
    {
        try {
            $lessons = Lesson::whereDoesntHave('attendances')
                ->where('lesson_date', '<=', now()->toDateString())
                ->with(['schedule.teacher', 'subject'])
                ->get();

            foreach ($lessons as $lesson) {
                if ($lesson->schedule && $lesson->schedule->teacher) {
                    $this->createAttendanceNotSetNotification($lesson, $lesson->schedule->teacher);
                }
            }

            Log::info("Checked lessons without attendance, found: " . count($lessons));

        } catch (\Exception $e) {
            Log::error('Error checking lessons without attendance', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Проверить просроченные домашние задания
     */
    public function checkOverdueHomeworks()
    {
        try {
            $homeworks = Homework::where('due_date', '<', now())
                ->with(['teacher', 'subject', 'submissions'])
                ->get();

            foreach ($homeworks as $homework) {
                $overdueSubmissions = $homework->submissions->where('status', 'overdue');

                if ($overdueSubmissions->isNotEmpty()) {
                    $this->createHomeworkOverdueNotification(
                        $homework,
                        $overdueSubmissions->values()->toArray()
                    );
                }
            }

            Log::info("Checked overdue homeworks, processed: " . count($homeworks));

        } catch (\Exception $e) {
            Log::error('Error checking overdue homeworks', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Автоматически проверять все необходимые условия
     */
    public function runAutomaticChecks()
    {
        Log::info('Starting automatic notification checks');

        $this->checkLessonsWithoutGrades();
        $this->checkLessonsWithoutAttendance();
        $this->checkOverdueHomeworks();

        Log::info('Completed automatic notification checks');
    }
}
