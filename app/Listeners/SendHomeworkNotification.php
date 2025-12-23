<?php

namespace App\Listeners;

use App\Events\HomeworkAssigned;
use App\Models\Notification;
use App\Models\ParentNotificationSetting;
use App\Models\ParentStudent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события назначения домашнего задания
 *
 * Отправляет уведомления ученикам класса и их родителям
 * при назначении нового домашнего задания.
 */
class SendHomeworkNotification implements ShouldQueue
{
    /**
     * Обработать событие
     *
     * @param HomeworkAssigned $event
     * @return void
     */
    public function handle(HomeworkAssigned $event): void
    {
        $homework = $event->homework;

        try {
            DB::beginTransaction();

            // Загружаем связанные данные
            $homework->load(['subject', 'teacher', 'schoolClass.students']);

            // Получаем учеников класса
            $students = $homework->schoolClass->students;

            if ($students->isEmpty()) {
                Log::warning('Нет учеников в классе для отправки уведомлений о домашнем задании', [
                    'homework_id' => $homework->id,
                    'class_id' => $homework->school_class_id,
                ]);
                DB::commit();
                return;
            }

            // Создаем уведомления для каждого ученика
            foreach ($students as $student) {
                $this->createStudentNotification($homework, $student->id);

                // Создаем уведомления для родителей ученика
                $this->createParentNotifications($homework, $student->id);
            }

            DB::commit();

            Log::info('Уведомления о домашнем задании успешно созданы', [
                'homework_id' => $homework->id,
                'class_id' => $homework->school_class_id,
                'students_count' => $students->count(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Ошибка при создании уведомлений о домашнем задании', [
                'homework_id' => $homework->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Создать уведомление для ученика
     *
     * @param \App\Models\Homework $homework
     * @param int $studentId
     * @return void
     */
    protected function createStudentNotification($homework, int $studentId): void
    {
        $subjectName = $homework->subject->name ?? 'Предмет';
        $teacherName = $homework->teacher->full_name ?? 'Учитель';
        $dueDate = $homework->due_date?->format('d.m.Y') ?? 'не указан';

        $title = "Новое домашнее задание";

        $message = sprintf(
            "Назначено домашнее задание по предмету \"%s\".\nТема: %s\nСрок сдачи: %s\nУчитель: %s",
            $subjectName,
            $homework->title,
            $dueDate,
            $teacherName
        );

        if ($homework->description) {
            $message .= "\n\nОписание: " . $homework->description;
        }

        if ($homework->max_points) {
            $message .= "\nМаксимальный балл: " . $homework->max_points;
        }

        Notification::create([
            'user_id' => $studentId,
            'title' => $title,
            'message' => $message,
            'type' => Notification::TYPE_HOMEWORK_ASSIGNED,
            'priority' => Notification::PRIORITY_MEDIUM,
            'category' => Notification::CATEGORY_HOMEWORK,
            'related_type' => get_class($homework),
            'related_id' => $homework->id,
            'data' => [
                'homework_id' => $homework->id,
                'subject_id' => $homework->subject_id,
                'subject_name' => $subjectName,
                'title' => $homework->title,
                'teacher_name' => $teacherName,
                'assigned_date' => $homework->assigned_date?->format('Y-m-d'),
                'due_date' => $homework->due_date?->format('Y-m-d'),
                'max_points' => $homework->max_points,
            ],
        ]);
    }

    /**
     * Создать уведомления для родителей ученика
     *
     * @param \App\Models\Homework $homework
     * @param int $studentId
     * @return void
     */
    protected function createParentNotifications($homework, int $studentId): void
    {
        // Получаем активные связи родитель-ученик
        $parentStudents = ParentStudent::where('student_id', $studentId)
            ->where('status', ParentStudent::STATUS_ACTIVE)
            ->with('parent')
            ->get();

        foreach ($parentStudents as $parentStudent) {
            // Проверяем настройки уведомлений родителя
            $settings = ParentNotificationSetting::where('parent_id', $parentStudent->parent_id)
                ->where('student_id', $studentId)
                ->first();

            // Если настроек нет, создаем с дефолтными значениями
            if (!$settings) {
                $settings = ParentNotificationSetting::getOrCreateDefault(
                    $parentStudent->parent_id,
                    $studentId
                );
            }

            // Проверяем, нужно ли отправлять уведомление
            if (!$settings->shouldNotifyHomeworkAssigned()) {
                continue;
            }

            $this->createParentNotification($homework, $parentStudent->parent_id, $studentId);
        }
    }

    /**
     * Создать уведомление для родителя
     *
     * @param \App\Models\Homework $homework
     * @param int $parentId
     * @param int $studentId
     * @return void
     */
    protected function createParentNotification($homework, int $parentId, int $studentId): void
    {
        $subjectName = $homework->subject->name ?? 'Предмет';
        $teacherName = $homework->teacher->full_name ?? 'Учитель';
        $dueDate = $homework->due_date?->format('d.m.Y') ?? 'не указан';

        // Получаем имя ученика
        $student = \App\Models\User::find($studentId);
        $studentName = $student?->full_name ?? 'Ученик';

        $title = "Домашнее задание для ребенка";

        $message = sprintf(
            "Вашему ребенку %s назначено домашнее задание по предмету \"%s\".\nТема: %s\nСрок сдачи: %s\nУчитель: %s",
            $studentName,
            $subjectName,
            $homework->title,
            $dueDate,
            $teacherName
        );

        if ($homework->description) {
            $message .= "\n\nОписание: " . $homework->description;
        }

        if ($homework->max_points) {
            $message .= "\nМаксимальный балл: " . $homework->max_points;
        }

        Notification::create([
            'user_id' => $parentId,
            'title' => $title,
            'message' => $message,
            'type' => Notification::TYPE_HOMEWORK_ASSIGNED,
            'priority' => Notification::PRIORITY_MEDIUM,
            'category' => Notification::CATEGORY_HOMEWORK,
            'related_type' => get_class($homework),
            'related_id' => $homework->id,
            'data' => [
                'homework_id' => $homework->id,
                'student_id' => $studentId,
                'student_name' => $studentName,
                'subject_id' => $homework->subject_id,
                'subject_name' => $subjectName,
                'title' => $homework->title,
                'teacher_name' => $teacherName,
                'assigned_date' => $homework->assigned_date?->format('Y-m-d'),
                'due_date' => $homework->due_date?->format('Y-m-d'),
                'max_points' => $homework->max_points,
            ],
        ]);
    }
}
