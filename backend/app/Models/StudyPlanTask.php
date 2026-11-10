<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyPlanTask extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'target' => 'array', 'completed_at' => 'datetime'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }
}
