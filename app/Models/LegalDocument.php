<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $fillable = [
        'slug',
        'audience',
        'title',
        'content',
        'version',
        'effective_at',
        'is_published',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'is_published' => 'boolean',
    ];
}
