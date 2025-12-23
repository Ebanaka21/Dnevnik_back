<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'curriculum_plan_detail_id',
        'lesson_type',
        'lesson_topic',
        'learning_objectives',
        'materials',
        'homework_assignment',
        'homework_due_date'
    ];

    protected $casts = [
        'homework_due_date' => 'date'
    ];

    // Связи
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function curriculumPlanDetail(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlanDetail::class);
    }

    // Методы
    public function getSubject()
    {
        return $this->curriculumPlanDetail->getSubject();
    }

    public function getSchoolClass()
    {
        return $this->curriculumPlanDetail->getSchoolClass();
    }

    public function getTeacher()
    {
        return $this->curriculumPlanDetail->getTeacher();
    }

    public function getThematicBlock()
    {
        return $this->curriculumPlanDetail->thematicBlock ?? null;
    }

    public function isHomeworkOverdue()
    {
        if (!$this->homework_due_date) {
            return false;
        }

        return now()->toDateString() > $this->homework_due_date;
    }
}
