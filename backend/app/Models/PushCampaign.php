<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class PushCampaign extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'body', 'action_label'];

    protected $guarded = ['id'];

    protected $casts = ['audience' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
