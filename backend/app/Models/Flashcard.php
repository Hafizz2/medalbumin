<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Flashcard extends Model
{
    use HasTranslations;

    public array $translatable = ['front', 'prompt', 'back', 'detail'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn (Flashcard $c) => $c->deck?->refreshCount());
        static::deleted(fn (Flashcard $c) => $c->deck?->refreshCount());
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(FlashcardDeck::class, 'flashcard_deck_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
