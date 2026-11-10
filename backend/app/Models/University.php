<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }
}
