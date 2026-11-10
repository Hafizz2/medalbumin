<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class LabValue extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
