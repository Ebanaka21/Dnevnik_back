<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'subject_id',
        'school_class_id',
        'teacher_id',
        'title',
        'description',
        'date',
        'lesson_number',
        'start_time',
        'end_time',
        'homework_assignment',
    ];

    protected $casts = [
        'date' => 'date',
        'lesson_number' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
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
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'original_lesson_id');
    }
}
