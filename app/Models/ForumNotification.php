<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'actor_id', 'type', 'title', 'body', 'url', 'data', 'read_at',
    ];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
