<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCode extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['code_hash', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
