<?php

namespace App\Listeners;

use App\Events\GradeCreated;
use App\Models\Notification;
use App\Models\ParentNotificationSetting;
use App\Models\ParentStudent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события создания оценки
 *
 * Отправляет уведомления ученику и его родителям
 * при создании новой оценки в системе.
 */
class SendGradeNotification implements ShouldQueue
{
    /**
     * Обработать событие
     *
     * @param GradeCreated $event
     * @return void
     */
    public function handle(GradeCreated $event): void
    {
        $grade = $event->grade;

        try {
            DB::beginTransaction();

            // Загружаем связанные данные
            $grade->load(['student', 'subject', 'teacher', 'gradeType']);

            // Определяем, является ли оценка плохой (2-3)
            $isBadGrade = $grade->value <= 3;

            // Создаем уведомление для ученика
            $this->createStudentNotification($grade, $isBadGrade);

            // Создаем уведомления для родителей (только если оценка плохая)
            if ($isBadGrade) {
                $this->createParentNotifications($grade);
            }

            DB::commit();

            Log::info('Уведомления об оценке успешно созданы', [
                'grade_id' => $grade->id,
                'student_id' => $grade->student_id,
                'value' => $grade->value,
                'is_bad_grade' => $isBadGrade,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Ошибка при создании уведомлений об оценке', [
                'grade_id' => $grade->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Создать уведомление для ученика
     *
     * @param \App\Models\Grade $grade
     * @param bool $isBadGrade
     * @return void
     */
    protected function createStudentNotification($grade, bool $isBadGrade): void
    {
        $subjectName = $grade->subject->name ?? 'Предмет';
        $gradeTypeName = $grade->gradeType->name ?? 'Оценка';
        $teacherName = $grade->teacher->full_name ?? 'Учитель';

        $title = $isBadGrade
            ? "Получена неудовлетворительная оценка"
            : "Получена новая оценка";

        $message = sprintf(
            "Вы получили оценку %d по предмету \"%s\" (%s) от %s.",
            $grade->value,
            $subjectName,
            $gradeTypeName,
            $teacherName
        );

        if ($grade->comment) {
            $message .= "\nКомментарий: " . $grade->comment;
        }

        Notification::create([
            'user_id' => $grade->student_id,
            'title' => $title,
            'message' => $message,
            'type' => $isBadGrade ? Notification::TYPE_BAD_GRADE : Notification::TYPE_GOOD_GRADE,
            'priority' => $isBadGrade ? Notification::PRIORITY_HIGH : Notification::PRIORITY_MEDIUM,
            'category' => Notification::CATEGORY_ACADEMIC,
            'related_type' => get_class($grade),
            'related_id' => $grade->id,
            'data' => [
                'grade_id' => $grade->id,
                'subject_id' => $grade->subject_id,
                'subject_name' => $subjectName,
                'grade_value' => $grade->value,
                'grade_type' => $gradeTypeName,
                'teacher_name' => $teacherName,
                'date' => $grade->date?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Создать уведомления для родителей
     *
     * @param \App\Models\Grade $grade
     * @return void
     */
    protected function createParentNotifications($grade): void
    {
        // Получаем активные связи родитель-ученик
        $parentStudents = ParentStudent::where('student_id', $grade->student_id)
            ->where('status', ParentStudent::STATUS_ACTIVE)
            ->with('parent')
            ->get();

        foreach ($parentStudents as $parentStudent) {
            // Проверяем настройки уведомлений родителя
            $settings = ParentNotificationSetting::where('parent_id', $parentStudent->parent_id)
                ->where('student_id', $grade->student_id)
                ->first();

            // Если настроек нет, создаем с дефолтными значениями
            if (!$settings) {
                $settings = ParentNotificationSetting::getOrCreateDefault(
                    $parentStudent->parent_id,
                    $grade->student_id
                );
            }

            // Проверяем, нужно ли отправлять уведомление
            if (!$settings->shouldNotifyBadGrade($grade->value)) {
                continue;
            }

            $this->createParentNotification($grade, $parentStudent->parent_id);
        }
    }

    /**
     * Создать уведомление для родителя
     *
     * @param \App\Models\Grade $grade
     * @param int $parentId
     * @return void
     */
    protected function createParentNotification($grade, int $parentId): void
    {
        $subjectName = $grade->subject->name ?? 'Предмет';
        $gradeTypeName = $grade->gradeType->name ?? 'Оценка';
        $studentName = $grade->student->full_name ?? 'Ученик';
        $teacherName = $grade->teacher->full_name ?? 'Учитель';

        $title = "Неудовлетворительная оценка у ребенка";

        $message = sprintf(
            "Ваш ребенок %s получил оценку %d по предмету \"%s\" (%s) от %s.",
            $studentName,
            $grade->value,
            $subjectName,
            $gradeTypeName,
            $teacherName
        );

        if ($grade->comment) {
            $message .= "\nКомментарий учителя: " . $grade->comment;
        }

        Notification::create([
            'user_id' => $parentId,
            'title' => $title,
            'message' => $message,
            'type' => Notification::TYPE_BAD_GRADE,
            'priority' => Notification::PRIORITY_HIGH,
            'category' => Notification::CATEGORY_ACADEMIC,
            'related_type' => get_class($grade),
            'related_id' => $grade->id,
            'data' => [
                'grade_id' => $grade->id,
                'student_id' => $grade->student_id,
                'student_name' => $studentName,
                'subject_id' => $grade->subject_id,
                'subject_name' => $subjectName,
                'grade_value' => $grade->value,
                'grade_type' => $gradeTypeName,
                'teacher_name' => $teacherName,
                'date' => $grade->date?->format('Y-m-d'),
            ],
        ]);
    }
}
