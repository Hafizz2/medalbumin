<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'raw_score' => 'float',
        'is_female' => 'boolean',
        'has_managerial' => 'boolean',
        'specialty_ids' => 'array',
        'remind' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
