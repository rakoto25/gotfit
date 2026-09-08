<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumDiscussion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'channel_id', 'author_id', 'title', 'body', 'is_pinned', 'is_locked',
        'pinned_by', 'pinned_at', 'locked_by', 'locked_at', 'views_count',
        'last_activity_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'pinned_at' => 'datetime',
        'locked_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'views_count' => 'integer',
    ];

    public function channel()
    {
        return $this->belongsTo(ForumChannel::class, 'channel_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments()
    {
        return $this->hasMany(ForumComment::class, 'discussion_id');
    }

    public function reactions()
    {
        return $this->hasMany(ForumReaction::class, 'discussion_id');
    }

    public function mentions()
    {
        return $this->hasMany(ForumMention::class, 'discussion_id');
    }
}
