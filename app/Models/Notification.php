<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Модель уведомления
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property string|null $priority
 * @property string|null $category
 * @property array|null $data
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $related_type
 * @property int|null $related_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read User $user
 * @property-read Model|null $related
 */
class Notification extends Model
{
    /**
     * Типы уведомлений
     */
    const TYPE_BAD_GRADE = 'bad_grade';
    const TYPE_GOOD_GRADE = 'good_grade';
    const TYPE_ABSENCE = 'absence';
    const TYPE_LATE = 'late';
    const TYPE_HOMEWORK_ASSIGNED = 'homework_assigned';
    const TYPE_HOMEWORK_DEADLINE = 'homework_deadline';
    const TYPE_HOMEWORK_OVERDUE = 'homework_overdue';
    const TYPE_HOMEWORK_REVIEWED = 'homework_reviewed';
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_REPORT_READY = 'report_ready';
    const TYPE_OTHER = 'other';

    /**
     * Приоритеты уведомлений
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Категории уведомлений
     */
    const CATEGORY_ACADEMIC = 'academic';
    const CATEGORY_ATTENDANCE = 'attendance';
    const CATEGORY_HOMEWORK = 'homework';
    const CATEGORY_ADMINISTRATIVE = 'administrative';
    const CATEGORY_BEHAVIORAL = 'behavioral';

    /**
     * Поля, которые можно массово заполнять
     */
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'priority',
        'category',
        'data',
        'is_read',
        'read_at',
        'expires_at',
        'related_type',
        'related_id',
    ];

    /**
     * Касты атрибутов
     */
    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Отношения
     */

    /**
     * Пользователь, которому принадлежит уведомление
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Полиморфное отношение к связанной сущности
     * (Grade, Attendance, Homework и т.д.)
     *
     * @return MorphTo
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */

    /**
     * Только непрочитанные уведомления
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Только прочитанные уведомления
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Фильтр по приоритету
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $priority
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Фильтр по типу
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Фильтр по категории
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Только не истекшие уведомления
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Только истекшие уведомления
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now());
    }

    /**
     * Методы
     */

    /**
     * Проверить, истекло ли уведомление
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Отметить уведомление как прочитанное
     *
     * @return bool
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        $this->is_read = true;
        $this->read_at = now();

        return $this->save();
    }

    /**
     * Отметить уведомление как непрочитанное
     *
     * @return bool
     */
    public function markAsUnread(): bool
    {
        if (!$this->is_read) {
            return true;
        }

        $this->is_read = false;
        $this->read_at = null;

        return $this->save();
    }

    /**
     * Проверить, является ли уведомление срочным
     *
     * @return bool
     */
    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    /**
     * Проверить, является ли уведомление высокоприоритетным
     *
     * @return bool
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Получить цвет приоритета для UI
     *
     * @return string
     */
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_LOW => 'gray',
            default => 'gray',
        };
    }

    /**
     * Получить иконку типа уведомления
     *
     * @return string
     */
    public function getTypeIcon(): string
    {
        return match($this->type) {
            self::TYPE_BAD_GRADE, self::TYPE_GOOD_GRADE => '📝',
            self::TYPE_ABSENCE, self::TYPE_LATE => '📅',
            self::TYPE_HOMEWORK_ASSIGNED, self::TYPE_HOMEWORK_DEADLINE,
            self::TYPE_HOMEWORK_OVERDUE, self::TYPE_HOMEWORK_REVIEWED => '📚',
            self::TYPE_ANNOUNCEMENT => '📢',
            self::TYPE_REPORT_READY => '📊',
            default => '🔔',
        };
    }
}
