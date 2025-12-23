<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentClass extends Pivot
{
    protected $table = 'student_classes';

    protected $fillable = [
        'student_id',
        'school_class_id',
        'academic_year',
        'is_active',
        'enrolled_at',
        'graduated_at',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
        'graduated_at' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Устанавливаем дефолтные значения
            if (!$model->academic_year) {
                $model->academic_year = '2024-2025';
            }
            if (!isset($model->is_active)) {
                $model->is_active = true;
            }
            if (!$model->enrolled_at) {
                $model->enrolled_at = now();
            }
        });
    }

    /**
     * Отношения
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }
}
