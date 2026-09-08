<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color', 'is_official',
        'is_active', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'is_official' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function discussions()
    {
        return $this->hasMany(ForumDiscussion::class, 'channel_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
