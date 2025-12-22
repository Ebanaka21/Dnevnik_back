<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'teacher_id',
        'date',
        'status',
        'reason',
        'arrival_time',
        'lesson_number',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'arrival_time' => 'datetime:H:i',
        'lesson_number' => 'integer',
    ];

    /**
     * Отношения
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function comments()
    {
        return $this->morphMany(TeacherComment::class, 'commentable');
    }
}
