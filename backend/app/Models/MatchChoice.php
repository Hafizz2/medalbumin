<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchChoice extends Model
{
    protected $guarded = ['id'];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
