<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThematicBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_plan_id',
        'title',
        'description',
        'weeks_count',
        'order',
        'learning_objectives',
        'required_materials'
    ];

    protected $casts = [
        'weeks_count' => 'integer',
        'order' => 'integer',
        'learning_objectives' => 'array',
        'required_materials' => 'array'
    ];

    // Связи
    public function curriculumPlan(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class);
    }

    public function curriculumPlanDetails(): HasMany
    {
        return $this->hasMany(CurriculumPlanDetail::class);
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
