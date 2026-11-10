<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class VideoChapter extends Model
{
    use HasTranslations;

    public $timestamps = false;

    public array $translatable = ['title'];

    protected $guarded = ['id'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
