<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionReport extends Model
{
    public const REASONS = [
        'wrong_key' => 'Wrong answer key',
        'image_unclear' => 'Image unclear',
        'translation' => 'Translation',
        'typo' => 'Typo',
        'outdated' => 'Outdated guideline',
        'other' => 'Other',
    ];

    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
