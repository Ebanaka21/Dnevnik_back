<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Поля, которые можно массово заполнять
     */
    protected $fillable = [
        'name',                  // Имя
        'surname',               // Фамилия
        'second_name',           // Отчество

        'passport_series',
        'passport_number',
        'passport_issued_at',
        'passport_issued_by',
        'passport_code',

        'birthday',
        'gender',                // male | female

        'phone',
        'email',
        'password',
        'role',                  // Роль пользователя: admin, teacher, student, parent
    ];

    /**
     * Поля, которые скрываются при сериализации (в API)
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Касты атрибутов
     */
    protected $appends = [
        'full_name',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'passport_issued_at' => 'date',
            'birthday' => 'date',
            'password' => 'hashed',
        ];
    }

    /**
     * Полное ФИО (удобный аксессор)
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->surname,
            $this->name,
            $this->second_name
        ], function ($value) {
            return !empty(trim($value));
        });

        return trim(implode(' ', $parts)) ?: 'Неизвестный пользователь';
    }

    /**
     * Роль пользователя (удобный аксессор)
     */
    public function getRoleAttribute(): ?string
    {
        return $this->attributes['role'] ?? null;
    }

    /**
     * JWT: идентификатор пользователя
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT: дополнительные claims (можно добавить роль, права и т.д.)
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'user_id' => $this->id,
            'email'   => $this->email,
            'name'    => $this->getFullNameAttribute(),
            'role'    => $this->role ?? 'unknown',
        ];
    }

    /**
     * Отношения
     */

    public function taughtClasses()
    {
        return $this->hasMany(SchoolClass::class, 'class_teacher_id');
    }

    public function studentClassRelationships()
    {
        return $this->hasMany(StudentClass::class, 'student_id');
    }

    public function parentStudents()
    {
        return $this->hasMany(ParentStudent::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasManyThrough(
            User::class,
            ParentStudent::class,
            'parent_id',
            'id',
            'id',
            'student_id'
        );
    }

    public function parents()
    {
        return $this->belongsToMany(
            User::class,
            'parent_students',
            'student_id',
            'parent_id'
        )->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }

    public function grades()
    {
        return $this->hasMany(Grade::class, 'student_id');
    }

    public function taughtGrades()
    {
        return $this->hasMany(Grade::class, 'teacher_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function taughtAАttendances()
    {
        return $this->hasMany(Attendance::class, 'teacher_id');
    }

    public function homeworks()
    {
        return $this->hasMany(Homework::class, 'teacher_id');
    }

    public function homeworkSubmissions()
    {
        return $this->hasMany(HomeworkSubmission::class, 'student_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }

    public function replacementSchedules()
    {
        return $this->hasMany(Schedule::class, 'replacement_teacher_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function teacherComments()
    {
        return $this->hasMany(TeacherComment::class, 'teacher_id');
    }

    public function studentClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'student_classes', 'student_id', 'school_class_id')
                    ->withPivot('academic_year', 'is_active')
                    ->withTimestamps();
    }

    public function teacherClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_classes', 'teacher_id', 'school_class_id')
                    ->withPivot('academic_year', 'is_active')
                    ->withTimestamps();
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'student_classes', 'student_id', 'school_class_id')
                    ->withPivot('academic_year', 'is_active')
                    ->withTimestamps();
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects', 'teacher_id', 'subject_id')
                    ->withTimestamps();
    }

    public function curriculumPlans()
    {
        return $this->hasMany(CurriculumPlan::class, 'teacher_id');
    }

    /**
     * Проверка, заполнены ли паспортные данные
     */
    public function hasPassportData(): bool
    {
        return !empty($this->passport_series) &&
               !empty($this->passport_number) &&
               !empty($this->passport_issued_at);
    }

    /**
     * Проверить, является ли пользователь родителем
     *
     * @return bool
     */
    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    /**
     * Проверить, является ли пользователь учеником
     *
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Проверить, является ли пользователь учителем
     *
     * @return bool
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Проверить, является ли пользователь администратором
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Проверить, имеет ли родитель доступ к данным ученика
     *
     * @param int $studentId
     * @return bool
     */
    public function hasAccessToStudent(int $studentId): bool
    {
        if (!$this->isParent()) {
            return false;
        }

        return $this->children()->where('student_id', $studentId)->exists();
    }

    /**
     * Проверить, является ли пользователь родителем конкретного ученика
     *
     * @param int $studentId
     * @return bool
     */
    public function isParentOf(int $studentId): bool
    {
        return $this->hasAccessToStudent($studentId);
    }

    /**
     * Строковое представление пользователя для Filament
     */
    public function __toString(): string
    {
        return $this->full_name;
    }
}
