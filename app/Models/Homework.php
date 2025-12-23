<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    protected $fillable = [
        'subject_id',
        'school_class_id',
        'teacher_id',
        'title',
        'description',
        'assigned_date',
        'due_date',
        'max_points',
        'is_active',
    ];

    protected $table = 'homeworks';

    protected $casts = [
        'assigned_date' => 'date',
        'due_date' => 'date',
        'max_points' => 'integer',
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
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function submissions()
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function teacherComments()
    {
        return $this->hasMany(TeacherComment::class);
    }

    public function polymorphicComments()
    {
        return $this->morphMany(TeacherComment::class, 'commentable');
    }
}
