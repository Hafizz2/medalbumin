<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Drug extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'category', 'dose', 'watch'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
