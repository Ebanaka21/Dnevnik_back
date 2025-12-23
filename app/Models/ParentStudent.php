<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Модель связи родитель-ученик
 *
 * @property int $id
 * @property int $parent_id
 * @property int $student_id
 * @property string $relationship
 * @property bool $is_primary
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property int|null $created_by
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read User $parent
 * @property-read User $student
 * @property-read User|null $creator
 * @property-read ParentNotificationSetting|null $notificationSettings
 */
class ParentStudent extends Model
{
    /**
     * Статусы связи
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REVOKED = 'revoked';

    /**
     * Типы родства
     */
    const RELATIONSHIP_MOTHER = 'mother';
    const RELATIONSHIP_FATHER = 'father';
    const RELATIONSHIP_GUARDIAN = 'guardian';
    const RELATIONSHIP_OTHER = 'other';

    /**
     * Поля, которые можно массово заполнять
     */
    protected $fillable = [
        'parent_id',
        'student_id',
        'relationship',
        'is_primary',
        'status',
        'verified_at',
        'created_by',
        'rejection_reason',
    ];

    /**
     * Касты атрибутов
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Отношения
     */

    /**
     * Родитель (пользователь с ролью parent)
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Ученик (пользователь с ролью student)
     *
     * @return BelongsTo
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Создатель связи (администратор или учитель)
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */

    /**
     * Только активные связи
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Только ожидающие подтверждения связи
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Связи для конкретного родителя
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $parentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForParent($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Связи для конкретного ученика
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $studentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Методы
     */

    /**
     * Подтвердить связь родитель-ученик
     *
     * @return bool
     */
    public function approve(): bool
    {
        $this->status = self::STATUS_ACTIVE;
        $this->verified_at = now();
        $this->rejection_reason = null;

        return $this->save();
    }

    /**
     * Отклонить связь родитель-ученик
     *
     * @param string|null $reason
     * @return bool
     */
    public function reject(?string $reason = null): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejection_reason = $reason;
        $this->verified_at = null;

        return $this->save();
    }

    /**
     * Отозвать связь родитель-ученик
     *
     * @return bool
     */
    public function revoke(): bool
    {
        $this->status = self::STATUS_REVOKED;

        return $this->save();
    }

    /**
     * Проверить, активна ли связь
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Проверить, ожидает ли связь подтверждения
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Проверить, отклонена ли связь
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Проверить, отозвана ли связь
     *
     * @return bool
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
