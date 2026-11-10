<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class QuestionOption extends Model
{
    use HasTranslations;

    public $timestamps = false;

    public array $translatable = ['text'];

    protected $guarded = ['id', 'pick_count'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
