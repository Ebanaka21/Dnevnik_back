<?php

namespace App\Listeners;

use App\Events\AttendanceMarked;
use App\Models\Notification;
use App\Models\ParentNotificationSetting;
use App\Models\ParentStudent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события отметки посещаемости
 *
 * Отправляет уведомления ученику и его родителям
 * при отметке отсутствия или опоздания.
 */
class SendAttendanceNotification implements ShouldQueue
{
    /**
     * Обработать событие
     *
     * @param AttendanceMarked $event
     * @return void
     */
    public function handle(AttendanceMarked $event): void
    {
        $attendance = $event->attendance;

        try {
            DB::beginTransaction();

            // Загружаем связанные данные
            $attendance->load(['student', 'subject', 'teacher']);

            // Проверяем, нужно ли отправлять уведомления
            // Отправляем только при отсутствии или опоздании
            if (!in_array($attendance->status, ['absent', 'late'])) {
                DB::commit();
                return;
            }

            // Создаем уведомление для ученика
            $this->createStudentNotification($attendance);

            // Создаем уведомления для родителей
            $this->createParentNotifications($attendance);

            DB::commit();

            Log::info('Уведомления о посещаемости успешно созданы', [
                'attendance_id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'status' => $attendance->status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Ошибка при создании уведомлений о посещаемости', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Создать уведомление для ученика
     *
     * @param \App\Models\Attendance $attendance
     * @return void
     */
    protected function createStudentNotification($attendance): void
    {
        $subjectName = $attendance->subject->name ?? 'Предмет';
        $teacherName = $attendance->teacher->full_name ?? 'Учитель';
        $date = $attendance->date?->format('d.m.Y') ?? 'сегодня';

        $isAbsent = $attendance->status === 'absent';
        $isLate = $attendance->status === 'late';

        $title = $isAbsent ? "Отмечено отсутствие" : "Отмечено опоздание";

        $message = sprintf(
            "Вы %s на уроке \"%s\" %s (учитель: %s).",
            $isAbsent ? 'отсутствовали' : 'опоздали',
            $subjectName,
            $date,
            $teacherName
        );

        if ($attendance->reason) {
            $message .= "\nПричина: " . $attendance->reason;
        }

        if ($isLate && $attendance->arrival_time) {
            $message .= "\nВремя прихода: " . $attendance->arrival_time->format('H:i');
        }

        if ($attendance->comment) {
            $message .= "\nКомментарий: " . $attendance->comment;
        }

        Notification::create([
            'user_id' => $attendance->student_id,
            'title' => $title,
            'message' => $message,
            'type' => $isAbsent ? Notification::TYPE_ABSENCE : Notification::TYPE_LATE,
            'priority' => $isAbsent ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
            'category' => Notification::CATEGORY_ATTENDANCE,
            'related_type' => get_class($attendance),
            'related_id' => $attendance->id,
            'data' => [
                'attendance_id' => $attendance->id,
                'subject_id' => $attendance->subject_id,
                'subject_name' => $subjectName,
                'status' => $attendance->status,
                'teacher_name' => $teacherName,
                'date' => $attendance->date?->format('Y-m-d'),
                'lesson_number' => $attendance->lesson_number,
                'arrival_time' => $attendance->arrival_time?->format('H:i'),
            ],
        ]);
    }

    /**
     * Создать уведомления для родителей
     *
     * @param \App\Models\Attendance $attendance
     * @return void
     */
    protected function createParentNotifications($attendance): void
    {
        // Получаем активные связи родитель-ученик
        $parentStudents = ParentStudent::where('student_id', $attendance->student_id)
            ->where('status', ParentStudent::STATUS_ACTIVE)
            ->with('parent')
            ->get();

        foreach ($parentStudents as $parentStudent) {
            // Проверяем настройки уведомлений родителя
            $settings = ParentNotificationSetting::where('parent_id', $parentStudent->parent_id)
                ->where('student_id', $attendance->student_id)
                ->first();

            // Если настроек нет, создаем с дефолтными значениями
            if (!$settings) {
                $settings = ParentNotificationSetting::getOrCreateDefault(
                    $parentStudent->parent_id,
                    $attendance->student_id
                );
            }

            // Проверяем, нужно ли отправлять уведомление
            $shouldNotify = false;
            if ($attendance->status === 'absent' && $settings->shouldNotifyAbsence()) {
                $shouldNotify = true;
            } elseif ($attendance->status === 'late' && $settings->shouldNotifyLate()) {
                $shouldNotify = true;
            }

            if (!$shouldNotify) {
                continue;
            }

            $this->createParentNotification($attendance, $parentStudent->parent_id);
        }
    }

    /**
     * Создать уведомление для родителя
     *
     * @param \App\Models\Attendance $attendance
     * @param int $parentId
     * @return void
     */
    protected function createParentNotification($attendance, int $parentId): void
    {
        $subjectName = $attendance->subject->name ?? 'Предмет';
        $studentName = $attendance->student->full_name ?? 'Ученик';
        $teacherName = $attendance->teacher->full_name ?? 'Учитель';
        $date = $attendance->date?->format('d.m.Y') ?? 'сегодня';

        $isAbsent = $attendance->status === 'absent';
        $isLate = $attendance->status === 'late';

        $title = $isAbsent ? "Отсутствие ребенка на уроке" : "Опоздание ребенка на урок";

        $message = sprintf(
            "Ваш ребенок %s %s на уроке \"%s\" %s (учитель: %s).",
            $studentName,
            $isAbsent ? 'отсутствовал' : 'опоздал',
            $subjectName,
            $date,
            $teacherName
        );

        if ($attendance->reason) {
            $message .= "\nПричина: " . $attendance->reason;
        }

        if ($isLate && $attendance->arrival_time) {
            $message .= "\nВремя прихода: " . $attendance->arrival_time->format('H:i');
        }

        if ($attendance->comment) {
            $message .= "\nКомментарий учителя: " . $attendance->comment;
        }

        Notification::create([
            'user_id' => $parentId,
            'title' => $title,
            'message' => $message,
            'type' => $isAbsent ? Notification::TYPE_ABSENCE : Notification::TYPE_LATE,
            'priority' => $isAbsent ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
            'category' => Notification::CATEGORY_ATTENDANCE,
            'related_type' => get_class($attendance),
            'related_id' => $attendance->id,
            'data' => [
                'attendance_id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'student_name' => $studentName,
                'subject_id' => $attendance->subject_id,
                'subject_name' => $subjectName,
                'status' => $attendance->status,
                'teacher_name' => $teacherName,
                'date' => $attendance->date?->format('Y-m-d'),
                'lesson_number' => $attendance->lesson_number,
                'arrival_time' => $attendance->arrival_time?->format('H:i'),
            ],
        ]);
    }
}
