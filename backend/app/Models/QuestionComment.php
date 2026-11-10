<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionComment extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_faculty' => 'boolean',
        'is_verified' => 'boolean',
        'answered_at' => 'datetime',
        'flags_resolved_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(QuestionComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(QuestionComment::class, 'parent_id');
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_votes', 'comment_id');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(CommentFlag::class, 'comment_id');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', 'visible');
    }
}
