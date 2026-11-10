<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['exam_date' => 'date', 'start_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(StudyPlanTask::class)->orderBy('date')->orderBy('position');
    }

    public function totalDays(): int
    {
        return max(1, (int) $this->start_date->diffInDays($this->exam_date) + 1);
    }

    public function dayNumber(): int
    {
        return min($this->totalDays(), max(1, (int) $this->start_date->diffInDays(today()) + 1));
    }

    /** 1 = learn all departments, 2 = revise weak areas, 3 = mocks. */
    public function phase(?\DateTimeInterface $date = null): int
    {
        $day = $date ? (int) $this->start_date->diffInDays($date) + 1 : $this->dayNumber();
        $ratio = $day / $this->totalDays();

        return $ratio <= 0.6 ? 1 : ($ratio <= 0.85 ? 2 : 3);
    }
}
