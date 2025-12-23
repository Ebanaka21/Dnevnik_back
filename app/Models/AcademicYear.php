<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_name',
        'start_date',
        'end_date',
        'total_weeks',
        'is_active',
        'description'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'total_weeks' => 'integer'
    ];

    // Связи
    public function academicWeeks(): HasMany
    {
        return $this->hasMany(AcademicWeek::class);
    }

    public function schoolClasses()
    {
        return $this->hasMany(SchoolClass::class, 'academic_year', 'year_name');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Методы
    public function getCurrentWeek()
    {
        $today = now()->toDateString();
        return $this->academicWeeks()
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();
    }

    public function getWeekByDate($date)
    {
        return $this->academicWeeks()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}
