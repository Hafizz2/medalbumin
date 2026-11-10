<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class FlashcardDeck extends Model
{
    use HasTranslations;

    public array $translatable = ['title'];

    protected $guarded = ['id'];

    protected $casts = ['is_official' => 'boolean'];

    public function cards(): HasMany
    {
        return $this->hasMany(Flashcard::class)->orderBy('sort');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Official published decks plus the user's own decks. */
    public function scopeAvailableTo(Builder $q, User $user): Builder
    {
        return $q->where(function (Builder $w) use ($user) {
            $w->where(fn ($o) => $o->where('is_official', true)->where('status', 'published'))
                ->orWhere('user_id', $user->id);
        });
    }

    public function pathLabel(): string
    {
        if ($this->subcategory) {
            return $this->subcategory->name;
        }

        return $this->department?->short_name ?: ($this->department?->name ?? 'All');
    }

    public function refreshCount(): void
    {
        $this->forceFill(['cards_count' => $this->cards()->count()])->saveQuietly();
    }
}
