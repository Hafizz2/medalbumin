<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'option_order' => 'array',
        'is_correct' => 'boolean',
        'is_flagged' => 'boolean',
        'answered_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }
}
