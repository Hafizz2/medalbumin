<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['cutoff' => 'float'];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function approvalRate(): ?int
    {
        return $this->applicants > 0 ? (int) round($this->accepted / $this->applicants * 100) : null;
    }
}
