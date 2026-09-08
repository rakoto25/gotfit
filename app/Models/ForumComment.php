<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['discussion_id', 'author_id', 'parent_id', 'body'];

    public function discussion()
    {
        return $this->belongsTo(ForumDiscussion::class, 'discussion_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reactions()
    {
        return $this->hasMany(ForumReaction::class, 'comment_id');
    }

    public function mentions()
    {
        return $this->hasMany(ForumMention::class, 'comment_id');
    }
}
