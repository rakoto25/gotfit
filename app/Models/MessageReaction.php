<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    use HasFactory;

    protected $table = 'message_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
    ];

    /**
     * Message concerné par la réaction.
     */
    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    /**
     * Utilisateur qui a réagi.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
