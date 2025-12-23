<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'subject_id',
        'school_class_id',
        'teacher_id',
        'replacement_teacher_id',
        'day_of_week',
        'lesson_number',
        'start_time',
        'end_time',
        'classroom',
        'effective_from',
        'effective_to',
        'academic_year',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'lesson_number' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Отношения
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function replacementTeacher()
    {
        return $this->belongsTo(User::class, 'replacement_teacher_id');
    }
}
