<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'subject_code',
        'description',
        'hours_per_week',
        'is_active',
    ];

    protected $casts = [
        'hours_per_week' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Отношения
     */
    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function homeworks()
    {
        return $this->hasMany(Homework::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function curriculumPlans()
    {
        return $this->hasMany(CurriculumPlan::class);
    }

    /**
     * Получить полное название предмета с кодом
     */
    public function getFullNameAttribute()
    {
        return $this->subject_code ? "{$this->name} ({$this->subject_code})" : $this->name;
    }

    /**
     * Получить часы в неделю в формате строки
     */
    public function getHoursDisplayAttribute()
    {
        $hours = $this->hours_per_week;
        $hour = $hours % 10;
        $hoursEnd = $hours % 100;

        if ($hoursEnd >= 11 && $hoursEnd <= 19) {
            $ending = 'часов';
        } else {
            switch ($hour) {
                case 1:
                    $ending = 'час';
                    break;
                case 2:
                case 3:
                case 4:
                    $ending = 'часа';
                    break;
                default:
                    $ending = 'часов';
            }
        }

        return "{$this->hours_per_week} {$ending} в неделю";
    }
}
