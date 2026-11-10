<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivity extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
