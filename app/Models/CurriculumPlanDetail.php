<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumPlanDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_plan_id',
        'thematic_block_id',
        'academic_week_id',
        'lessons_per_week',
        'weekly_objectives',
        'materials_needed'
    ];

    protected $casts = [
        'lessons_per_week' => 'integer',
        'materials_needed' => 'array'
    ];

    // Связи
    public function curriculumPlan(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class);
    }

    public function thematicBlock(): BelongsTo
    {
        return $this->belongsTo(ThematicBlock::class);
    }

    public function academicWeek(): BelongsTo
    {
        return $this->belongsTo(AcademicWeek::class);
    }

    public function lessonPlans(): HasMany
    {
        return $this->hasMany(LessonPlan::class);
    }

    // Методы
    public function getSubject()
    {
        return $this->curriculumPlan->subject ?? null;
    }

    public function getSchoolClass()
    {
        return $this->curriculumPlan->schoolClass ?? null;
    }

    public function getTeacher()
    {
        return $this->curriculumPlan->teacher ?? null;
    }
}
