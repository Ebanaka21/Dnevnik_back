<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReport extends Model
{
    protected $fillable = [
        'student_id',
        'school_class_id',
        'period_start',
        'period_end',
        'total_grades',
        'average_grade',
        'attendance_percentage',
        'report_data',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'average_grade' => 'decimal:2',
        'attendance_percentage' => 'decimal:2',
        'report_data' => 'array',
    ];

    /**
     * Отношения с обязательной проверкой на существование
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')
            ->withDefault([
                'name' => 'Неизвестный',
                'surname' => 'ученик',
                'second_name' => ''
            ]);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id')
            ->withDefault([
                'name' => 'Неизвестный',
                'year' => null,
                'letter' => 'класс'
            ]);
    }

    /**
     * Аксессоры с защитой от null
     */
    public function getFormattedAverageGradeAttribute(): string
    {
        if (is_null($this->average_grade)) {
            return '-';
        }
        return number_format($this->average_grade, 2, ',', ' ');
    }

    public function getFormattedAttendancePercentageAttribute(): string
    {
        if (is_null($this->attendance_percentage)) {
            return '-';
        }
        return number_format($this->attendance_percentage, 2, ',', ' ') . '%';
    }

    public function getPeriodLabelAttribute(): string
    {
        if (!$this->period_start || !$this->period_end) {
            return 'Период не указан';
        }

        return sprintf('%s - %s',
            $this->period_start->format('d.m.Y'),
            $this->period_end->format('d.m.Y')
        );
    }

    /**
     * Область запросов: отчеты за определенный период
     */
    public function scopeForPeriod($query, $start, $end)
    {
        return $query->whereBetween('period_start', [$start, $end])
                    ->whereBetween('period_end', [$start, $end]);
    }

    /**
     * Область запросов: отчеты ученика
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Область запросов: отчеты класса
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('school_class_id', $classId);
    }

    /**
     * Проверка валидности отчета
     */
    public function isValid(): bool
    {
        return !is_null($this->student_id)
            && !is_null($this->school_class_id)
            && !is_null($this->period_start)
            && !is_null($this->period_end);
    }

    /**
     * Получить полное имя ученика безопасно
     */
    public function getStudentFullNameAttribute(): string
    {
        return $this->student?->full_name ?? 'Неизвестный ученик';
    }

    /**
     * Получить полное название класса безопасно
     */
    public function getClassFullNameAttribute(): string
    {
        return $this->schoolClass?->full_name ?? 'Неизвестный класс';
    }
}
