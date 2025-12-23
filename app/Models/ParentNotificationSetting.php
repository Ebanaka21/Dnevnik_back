<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель настроек уведомлений родителя для конкретного ученика
 *
 * @property int $id
 * @property int $parent_id
 * @property int $student_id
 * @property bool $notify_bad_grades
 * @property bool $notify_absences
 * @property bool $notify_late
 * @property bool $notify_homework_assigned
 * @property bool $notify_homework_deadline
 * @property int $bad_grade_threshold
 * @property int $homework_deadline_days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read User $parent
 * @property-read User $student
 */
class ParentNotificationSetting extends Model
{
    /**
     * Типы уведомлений
     */
    const TYPE_BAD_GRADES = 'bad_grades';
    const TYPE_ABSENCES = 'absences';
    const TYPE_LATE = 'late';
    const TYPE_HOMEWORK_ASSIGNED = 'homework_assigned';
    const TYPE_HOMEWORK_DEADLINE = 'homework_deadline';

    /**
     * Поля, которые можно массово заполнять
     */
    protected $fillable = [
        'parent_id',
        'student_id',
        'notify_bad_grades',
        'notify_absences',
        'notify_late',
        'notify_homework_assigned',
        'notify_homework_deadline',
        'bad_grade_threshold',
        'homework_deadline_days',
    ];

    /**
     * Касты атрибутов
     */
    protected $casts = [
        'notify_bad_grades' => 'boolean',
        'notify_absences' => 'boolean',
        'notify_late' => 'boolean',
        'notify_homework_assigned' => 'boolean',
        'notify_homework_deadline' => 'boolean',
        'bad_grade_threshold' => 'integer',
        'homework_deadline_days' => 'integer',
    ];

    /**
     * Отношения
     */

    /**
     * Родитель
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Ученик
     *
     * @return BelongsTo
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Методы
     */

    /**
     * Проверить, нужно ли отправлять уведомление данного типа
     *
     * @param string $type Тип уведомления (bad_grades, absences, late, homework_assigned, homework_deadline)
     * @param int|null $gradeValue Значение оценки (для проверки порога)
     * @return bool
     */
    public function shouldNotify(string $type, ?int $gradeValue = null): bool
    {
        return match($type) {
            self::TYPE_BAD_GRADES => $this->notify_bad_grades &&
                                     ($gradeValue === null || $gradeValue <= $this->bad_grade_threshold),
            self::TYPE_ABSENCES => $this->notify_absences,
            self::TYPE_LATE => $this->notify_late,
            self::TYPE_HOMEWORK_ASSIGNED => $this->notify_homework_assigned,
            self::TYPE_HOMEWORK_DEADLINE => $this->notify_homework_deadline,
            default => false,
        };
    }

    /**
     * Получить настройки по умолчанию
     *
     * @return array
     */
    public static function getDefaultSettings(): array
    {
        return [
            'notify_bad_grades' => true,
            'notify_absences' => true,
            'notify_late' => true,
            'notify_homework_assigned' => true,
            'notify_homework_deadline' => false,
            'bad_grade_threshold' => 3,
            'homework_deadline_days' => 1,
        ];
    }

    /**
     * Получить или создать настройки с значениями по умолчанию
     *
     * @param int $parentId
     * @param int $studentId
     * @return self
     */
    public static function getOrCreateDefault(int $parentId, int $studentId): self
    {
        return self::firstOrCreate(
            [
                'parent_id' => $parentId,
                'student_id' => $studentId,
            ],
            self::getDefaultSettings()
        );
    }

    /**
     * Проверить, нужно ли уведомлять о плохой оценке
     *
     * @param int $gradeValue
     * @return bool
     */
    public function shouldNotifyBadGrade(int $gradeValue): bool
    {
        return $this->notify_bad_grades && $gradeValue <= $this->bad_grade_threshold;
    }

    /**
     * Проверить, нужно ли уведомлять об отсутствии
     *
     * @return bool
     */
    public function shouldNotifyAbsence(): bool
    {
        return $this->notify_absences;
    }

    /**
     * Проверить, нужно ли уведомлять об опоздании
     *
     * @return bool
     */
    public function shouldNotifyLate(): bool
    {
        return $this->notify_late;
    }

    /**
     * Проверить, нужно ли уведомлять о новом домашнем задании
     *
     * @return bool
     */
    public function shouldNotifyHomeworkAssigned(): bool
    {
        return $this->notify_homework_assigned;
    }

    /**
     * Проверить, нужно ли уведомлять о приближающемся дедлайне
     *
     * @return bool
     */
    public function shouldNotifyHomeworkDeadline(): bool
    {
        return $this->notify_homework_deadline;
    }

    /**
     * Получить количество дней до дедлайна для уведомления
     *
     * @return int
     */
    public function getHomeworkDeadlineDays(): int
    {
        return $this->homework_deadline_days;
    }

    /**
     * Получить порог плохой оценки
     *
     * @return int
     */
    public function getBadGradeThreshold(): int
    {
        return $this->bad_grade_threshold;
    }
}
