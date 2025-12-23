<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherComment extends Model
{
    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
        'content',
        'is_visible_to_student',
        'is_visible_to_parent',
    ];

    protected $casts = [
        'is_visible_to_student' => 'boolean',
        'is_visible_to_parent' => 'boolean',
    ];

    /**
     * Отношения
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commentable()
    {
        return $this->morphTo();
    }
}
