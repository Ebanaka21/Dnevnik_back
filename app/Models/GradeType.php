<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeType extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'description',
        'weight',
        'max_points',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'integer',
        'max_points' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Отношения
     */
    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * Получить максимальную оценку для типа оценок
     */
    public function getMaxGradeAttribute()
    {
        return $this->max_points;
    }

    /**
     * Получить вес оценки в процентах
     */
    public function getWeightPercentageAttribute()
    {
        $totalWeight = GradeType::where('is_active', true)->sum('weight');
        return round(($this->weight / $totalWeight) * 100, 2);
    }
}
