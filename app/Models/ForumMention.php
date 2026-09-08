<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumMention extends Model
{
    use HasFactory;

    protected $fillable = ['author_id', 'mentioned_user_id', 'discussion_id', 'comment_id'];
}
