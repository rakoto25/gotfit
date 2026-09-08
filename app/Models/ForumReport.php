<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id', 'discussion_id', 'comment_id', 'reason', 'details',
        'status', 'reviewed_by', 'reviewed_at', 'moderator_note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function discussion()
    {
        return $this->belongsTo(ForumDiscussion::class, 'discussion_id');
    }

    public function comment()
    {
        return $this->belongsTo(ForumComment::class, 'comment_id');
    }
}
