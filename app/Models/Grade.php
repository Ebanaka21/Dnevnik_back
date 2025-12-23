<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'grade_type_id',
        'teacher_id',
        'school_class_id',
        'value',
        'description',
        'date',
        'comment',
        'is_final',
    ];

    protected $casts = [
        'value' => 'integer',
        'date' => 'date',
        'is_final' => 'boolean',
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

    public function gradeType()
    {
        return $this->belongsTo(GradeType::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(\App\Models\SchoolClass::class, 'school_class_id');
    }
}
