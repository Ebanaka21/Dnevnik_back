<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $fillable = [
        'name',
        'year',
        'letter',
        'class_teacher_id',
        'max_students',
        'description',
        'academic_year',
        'is_active',
    ];

    protected $appends = ['full_name', 'students_count'];

    protected $casts = [
        'year' => 'integer',
        'max_students' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Отношения
     */
    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students()
    {
        return $this->hasManyThrough(
            User::class,
            StudentClass::class,
            'school_class_id',
            'id',
            'id',
            'student_id'
        )->whereNull('student_classes.graduated_at');
    }

    public function studentClasses()
    {
        return $this->hasMany(StudentClass::class);
    }

    public function homeworks()
    {
        return $this->hasMany(Homework::class, 'school_class_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'school_class_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'school_class_id');
    }

    public function curriculumPlans()
    {
        return $this->hasMany(CurriculumPlan::class, 'school_class_id');
    }

    public function grades()
    {
        return $this->hasManyThrough(
            Grade::class,
            StudentClass::class,
            'school_class_id',
            'student_id',
            'id',
            'student_id'
        );
    }

    public function attendances()
    {
        return $this->hasManyThrough(
            Attendance::class,
            StudentClass::class,
            'school_class_id',
            'student_id',
            'id',
            'student_id'
        );
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(User::class, 'student_classes', 'school_class_id', 'student_id')
                    ->using(StudentClass::class)
                    ->withPivot('academic_year', 'is_active', 'enrolled_at')
                    ->withTimestamps();
    }

    public function teacherUsers()
    {
        return $this->belongsToMany(User::class, 'teacher_classes', 'school_class_id', 'teacher_id')
                    ->withPivot('academic_year', 'is_active')
                    ->withTimestamps();
    }

    /**
     * Получить полное название класса с защитой от null
     */
    public function getFullNameAttribute(): string
    {
        if (!empty($this->name)) {
            $letter = $this->letter ? " \"{$this->letter}\"" : '';
            return trim($this->name . $letter);
        }

        if (!empty($this->year) || !empty($this->letter)) {
            $year = $this->year ?? '';
            $letter = $this->letter ? "\"{$this->letter}\"" : '';
            return trim($year . ' ' . $letter);
        }

        return 'Неизвестный класс';
    }

    /**
     * Получить короткое название класса (только год и буква)
     */
    public function getShortNameAttribute(): string
    {
        $parts = array_filter([
            $this->year,
            $this->letter ? "\"{$this->letter}\"" : null
        ], fn($value) => !empty($value));

        return implode(' ', $parts) ?: 'Н/Д';
    }

    /**
     * Получить количество учеников в классе
     */
    public function getStudentsCountAttribute(): int
    {
        return $this->students()->count();
    }

    /**
     * Получить активных учеников
     */
    public function getActiveStudentsCountAttribute(): int
    {
        return $this->studentUsers()->wherePivot('is_active', true)->count();
    }

    /**
     * Получить статус класса
     */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'Активный' : 'Неактивный';
    }

    /**
     * Получить название с учебным годом
     */
    public function getFullNameWithYearAttribute(): string
    {
        $name = $this->full_name;
        $academicYear = $this->academic_year ? " ({$this->academic_year})" : '';
        return $name . $academicYear;
    }

    /**
     * Scope для активных классов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для класса определенного года
     */
    public function scopeYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope для класса определенной буквы
     */
    public function scopeLetter($query, $letter)
    {
        return $query->where('letter', $letter);
    }

    /**
     * Scope для учебного года
     */
    public function scopeAcademicYear($query, $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    /**
     * Проверка заполненности класса
     */
    public function isFull(): bool
    {
        if (!$this->max_students) {
            return false;
        }
        return $this->students_count >= $this->max_students;
    }

    /**
     * Получить процент заполненности
     */
    public function getOccupancyPercentageAttribute(): float
    {
        if (!$this->max_students || $this->max_students <= 0) {
            return 0;
        }
        return round(($this->students_count / $this->max_students) * 100, 2);
    }
}
