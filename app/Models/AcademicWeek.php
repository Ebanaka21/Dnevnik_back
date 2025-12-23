<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicWeek extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'week_number',
        'start_date',
        'end_date',
        'is_holiday',
        'week_type',
        'notes'
    ];

    protected $casts = [
        'week_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_holiday' => 'boolean'
    ];

    // Связи
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function curriculumPlanDetails(): HasMany
    {
        return $this->hasMany(CurriculumPlanDetail::class);
    }

    // Scopes
    public function scopeRegular($query)
    {
        return $query->where('week_type', 'regular');
    }

    public function scopeHolidays($query)
    {
        return $query->where('is_holiday', true);
    }

    // Методы
    public function isCurrentWeek()
    {
        $today = now()->toDateString();
        return $this->start_date <= $today && $this->end_date >= $today;
    }

    public function getWorkingDays()
    {
        if ($this->is_holiday) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);

        $workingDays = 0;
        $current = $start->copy();

        while ($current <= $end) {
            if ($current->isWeekday()) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }
}
