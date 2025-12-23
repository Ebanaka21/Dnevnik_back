<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeworkSubmission extends Model
{
    protected $fillable = [
        'homework_id',
        'student_id',
        'content',
        'file_path',
        'submitted_at',
        'points_earned',
        'teacher_comment',
        'reviewed_at',
        'status',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'points_earned' => 'integer',
        'reviewed_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * Отношения
     */
    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
