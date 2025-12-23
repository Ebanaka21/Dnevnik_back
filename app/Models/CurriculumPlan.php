<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumPlan extends Model
{
    protected $fillable = [
        'school_class_id',
        'subject_id',
        'teacher_id',
        'academic_year',
        'hours_per_week',
    ];

    protected $casts = [
        'hours_per_week' => 'integer',
    ];

    /**
     * Отношения
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function thematicBlocks()
    {
        return $this->hasMany(ThematicBlock::class);
    }

    public function curriculumPlanDetails()
    {
        return $this->hasMany(CurriculumPlanDetail::class);
    }
}
