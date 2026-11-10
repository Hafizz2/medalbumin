<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentFlag extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = ['spam' => 'Spam / cheating', 'abuse' => 'Abusive language', 'wrong' => 'Medically wrong', 'other' => 'Other'];

    protected $guarded = ['id'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(QuestionComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
